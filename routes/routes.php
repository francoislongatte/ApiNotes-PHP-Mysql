<?php

// ID utilisateur - variable globale
$user_id = NULL;
/** 
 * Ajout de Couche intermédiaire pour authentifier chaque demande
 * Vérifier si la demande a clé API valide dans l'en-tête "Authorization"
 */
function authenticate ($req,$res,$next) {
    // Obtenir les en-têtes de requêtes
    $headers = $req->getHeader('Authorization')[0];
    $responseMessage = array();
    // Vérification de l'en-tête d'autorisation
    if (!empty($headers)) {
        $db = new DbHandler();

        // Obtenir la clé d'api
        $api_key = $headers;
        // Valider la clé API
        if (!$db->isValidApiKey($api_key)) {
            //  Clé API n'est pas présente dans la table des utilisateurs
            $responseMessage["error"] = true;
            $responseMessage["authMessage"] = "Accès Refusé. Clé API invalide";
            echoRespnse(401, $responseMessage, $res);

            return $newResponse = $next($req, $res);
        } else {
            global $user_id;
            // Obtenir l'ID utilisateur (clé primaire)
            $user_id = $db->getUserId($api_key);
            return $newResponse = $next($req, $res);
        }
    } else {
        // Clé API est absente dans la en-tête
        $responseMessage["error"] = true;
        $responseMessage["authMessage"] = "Clé API est manquante";
        //$newResponse = $next($requete, $response)
        echoRespnse(400, $responseMessage, $res);
        return $newResponse = $next($req, $res);
    }
};

/**
 * ----------- MÉTHODES sans authentification---------------------------------
 */
/**
 * Enregistrement de l'utilisateur
 * url - /register
 * methode - POST
 * params - name, email, password
 */ 

$app->get('/', function ($request, $response) {
    $messageBienvenue =  "Welcome to Slim Api for framework Js to create Note app!";
    return $this->view->render($response, 'test.php', [
        'messageBienvenue' => $messageBienvenue
    ]);
});



$app->post('/register', function($req,$response) {
    // vérifier les paramètres requises
    //  TODO -> verifier l'ecriture de la bonne version slim

    verifyRequiredParams(array('name', 'email', 'password'),$req,$response);

    $responseMessage = array();
    // lecture des params de post
    $data = $req->getParsedBody();

    $name = $data['name'];
    $email = $data['email'];
    $password = $data['password'];

    // valider adresse email
    validateEmail($email,$res);

    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);

    if ($res == USER_CREATED_SUCCESSFULLY) {
        $responseMessage["error"] = false;
        $responseMessage["message"] = "Vous êtes inscrit avec succès";
    } else if ($res == USER_CREATE_FAILED) {
        $responseMessage["error"] = true;
        $responseMessage["message"] = "Oops! Une erreur est survenue lors de l'inscription";
    } else if ($res == USER_ALREADY_EXISTED) {
        $responseMessage["error"] = true;
        $responseMessage["message"] = "Désolé, cet E-mail éxiste déja";
    }
    // echo de la repense  JSON
    echoRespnse(201, $responseMessage, $response);
});

/**
 * Login Utilisateur
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function($req,$res) {
    // vérifier les paramètres requises
    verifyRequiredParams(array('email', 'password'),req,$res);


    // lecture des params de post
    $data = $req->getParsedBody();
    // lecture des params de post
    $email = $data['email'];

    // valider l'adresse email
    validateEmail($email);

    $password = $data['password'];
    $responseMessage = array();

    $db = new DbHandler();
    // vérifier l'Email et le mot de passe sont corrects
    if ($db->checkLogin($email, $password)) {
        // obtenir l'utilisateur par email
        $user = $db->getUserByEmail($email);
        if ($user != NULL) {
            if($user["status"]==1){
                $responseMessage["error"] = false;
                $responseMessage['name'] = $user['name'];
                $responseMessage['email'] = $user['email'];
                $responseMessage['apiKey'] = $user['api_key'];
                $responseMessage['createdAt'] = $user['created_at'];
            }
            else {
                $responseMessage['error'] = true;
                $responseMessage['message'] = "Votre compte a été suspendu";
            }
        } else {
            // erreur inconnue est survenue
            $responseMessage['error'] = true;
            $responseMessage['message'] = "Une erreur est survenue. S'il vous plaît essayer à nouveau";
        }
    } else {
        // identificateurs de l'utilisateur sont erronés
        $responseMessage['error'] = true;
        $responseMessage['message'] = 'Échec de la connexion. identificateurs incorrectes';
    }

    echoRespnse(200, $responseMessage, $res);
});

$app->group('/notes', function () {


    $this->get('' , function ($req, $res) {
        global $user_id;
        $responseMessage = array();
        $db = new DbHandler();

        // aller chercher toutes les notes de l'utilisateur
        $result = $db->getAllUserNotes($user_id);

        $responseMessage["error"] = false;
        $responseMessage["notes"] = array();

        foreach ( $result as $note){
            array_push($responseMessage["notes"], $note);
        }

        echoRespnse(200, $responseMessage, $res);
    });

    $this->get('/{id}', function ($req, $res) {
        global $user_id;
        $response = array();
        $db = new DbHandler();
        $note_id = $req->getAttribute('id');

        //chercher tâche
        $result = $db->getNotes($note_id, $user_id);

        if ($result != NULL) {
            $responseMessage["error"] = false;
            $responseMessage["id"] = $result["id"];
            $responseMessage["title"] = $result["title"];
            $responseMessage["text"] = $result["text"];
            $responseMessage["status"] = $result["status"];
            $responseMessage["created_at"] = $result["created_at"];
            $responseMessage["modified_at"] = $result["modified_at"];
            echoRespnse(200, $responseMessage, $res);
        } else {
            $responseMessage["error"] = true;
            $responseMessage["message"] = "La ressource demandée n'existe pas";
            echoRespnse(404, $responseMessage, $res);
        }
    });


    $this->post('',function($req,$res){
        // vérifier les paramètres requises
        verifyRequiredParams(array('title','text'),$req,$res);

        $response = array();
        $data = $req->getParsedBody();
        $title = $data['title'];
        $text = $data['text'];

        global $user_id;
        $db = new DbHandler();
        //Création d'une nouvelle tâche
        $note_id = $db->createNote($user_id,$title,$text);

        if ($note_id != NULL) {
            $responseMessage["error"] = false;
            $responseMessage["message"] = "Tâche créé avec succès";
            $responseMessage["note_id"] = $note_id;
            echoRespnse(201, $responseMessage, $res);
        } else {
            $responseMessage["error"] = true;
            $responseMessage["message"] = "Impossible de créer la tâche. S'il vous plaît essayer à nouveau";
            echoRespnse(200, $responseMessage, $res);
        }
    });

    $this->post('/positionList',function($req,$res){
        // vérifier les paramètres requises
        verifyRequiredParams(array('notes'),$req,$res);

        $response = array();
        $notes = $req->getParsedBody()['notes'];

        global $user_id;
        $db = new DbHandler();
        //Création d'une nouvelle tâche
        $good = $db->positionNote($notes,$user_id);

        if ($notes != NULL) {
            $responseMessage["error"] = false;
            $responseMessage["message"] = "Mise à jour des positions de la liste effectué";
            $responseMessage["good"] = $good;
            echoRespnse(201, $responseMessage, $res);
        } else {
            $responseMessage["error"] = true;
            $responseMessage["message"] = "Mise à jour à echouer";
            $responseMessage["good"] = $good;
            echoRespnse(200, $responseMessage, $res);
        }
    });

    $this->put('/{id}', function ($req, $res) {
        // vérifier les paramètres requises
        verifyRequiredParams(array('title', 'text'),$req,$res);

        global $user_id;
        $data = $req->getParsedBody();

        $note_id = $req->getAttribute('id');;
        $title = $data['title'];
        $text = $data['text'];
        $status = $data['status'];

        $db = new DbHandler();
        $response = array();

        // Mise à jour de la tâche
        $result = $db->updateNote($user_id, $note_id, $title, $text, $status);
        if ($result) {
            // Tache mise à jour
            $responseMessage["error"] = false;
            $responseMessage["message"] = "Tâche mise à jour avec succès";
        } else {
            // Le mise à jour de la tâche a échoué.
            $responseMessage["error"] = true;
            $responseMessage["message"] = "Le mise à jour de la tâche a échoué. S'il vous plaît essayer de nouveau!";
        }
        echoRespnse(200, $responseMessage, $res);
    });
    $this->delete('/{id}', function ($req, $res) {
        global $user_id;

        $db = new DbHandler();
        $response = array();

        $note_id = $req->getAttribute('id');

        $result = $db->deleteNote($user_id, $note_id);
        if ($result) {
            // tâche supprimé avec succès
            $responseMessage["error"] = false;
            $responseMessage["message"] = "tâche supprimé avec succès";
        } else {
            // "échec de la suppression d'une tâche.
            $responseMessage["error"] = true;
            $responseMessage["message"] = "échec de la suppression d'une tâche. S'il vous plaît essayer de nouveau!";
        }
        echoRespnse(200, $responseMessage, $res);
    });
})->add(authenticate);

/**
 * Vérification params nécessaires posté ou non
 */
function verifyRequiredParams($required_fields,$req,$res) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    $isJson = json_decode(file_get_contents('php://input'), true);
    json_decode($isJson);


    // Manipulation paramsde la demande PUT
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        parse_str($req->getBody(), $request_params);
    }

    if(json_last_error() === JSON_ERROR_NONE){
        $error = false;
        if($isJson['notes'] == NULL){
            $error = true;
            $error_fields = 'Notes : Un tableau de notes  ';
        }
    }else{
        foreach ($required_fields as $field) {
            if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
                $error = true;
                $error_fields .= $field . ', ';
            }
        }
    }

    if ($error) {
        //Champ (s) requis sont manquants ou vides
        // echo erreur JSON et d'arrêter l'application
        $responseMessage = array();
        $responseMessage["error"] = true;
        $responseMessage["message"] = 'Champ(s) requis ' . substr($error_fields, 0, -2) . ' est (sont) manquant(s) ou vide(s)';
        echoRespnse(400, $responseMessage, $res);
    }
}

/**
 * Validation adresse e-mail
 */
function validateEmail($email,$res) {

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $responseMessage["error"] = true;
        $responseMessage["message"] = "Adresse e-mail n'est pas valide";
        echoRespnse(400, $responseMessage,$res);
    }
}

/**
 * Faisant écho à la réponse JSON au client
 * @param String $status_code  Code de réponse HTTP
 * @param Int $response response Json
 */
function echoRespnse($status_code, $response, $res) {

    // Code de réponse HTTP
    $res->withStatus($status_code);
    $res->withHeader('Content-type', 'application/json');
    $res->write(json_encode($response));

    if($status_code == '400' || $status_code == '401'){
        die(utf8_encode($res));
    }else{
        return utf8_encode($res);
    }

}