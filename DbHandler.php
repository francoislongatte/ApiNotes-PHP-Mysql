<?php

/**
 * Classe pour gérer toutes les opérations de db
 * Cette classe aura les méthodes CRUD pour les tables de base de données
 *

 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once __DIR__ . '/DbConnect.php';
        //Ouverture connexion db
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /* ------------- méthodes de la table `users` ------------------ */

    /**
     * Creation nouvel utilisateur
     * @param String $name nom complet de l'utilisateur
     * @param String $email email de connexion
     * @param String $password mot de passe de connexion
     */
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';



        // Vérifiez d'abord si l'utilisateur existe déjà dans db
        if (!$this->isUserExists($email)) {
            //Générer un hash de mot de passe
            $password_hash = PassHash::hash($password);
            // Générer API key
            $api_key = $this->generateApiKey();

            // requete d'insertion
            $stmt = $this->conn->prepare("INSERT INTO api_users ( name ,  email ,  password_hash ,  api_key) values (?,?,?,?)");
            $stmt->bindParam(1,$name);
            $stmt->bindParam(2,$email);
            $stmt->bindParam(3,$password_hash);
            $stmt->bindParam(4,$api_key);
            $result = $stmt->execute();

            $stmt = null;

            //Vérifiez pour une insertion réussie
            if ($result) {
                // Utilisateur inséré avec succès
                return USER_CREATED_SUCCESSFULLY;
            } else {
                //Échec de la création de l'utilisateur
                return USER_CREATE_FAILED;
            }
        } else {
            //Utilisateur avec la même email existait déjà dans la db
            return USER_ALREADY_EXISTED;
        }


    }

    /**
     * Vérification de connexion de l'utilisateur
     * @param String $email
     * @param String $password
     * @return boolean Le statut de connexion utilisateur réussite / échec
     */
    public function checkLogin($email, $password) {
        // Obtention de l'utilisateur par email
        $stmt = $this->conn->prepare("SELECT password_hash FROM api_users WHERE email = ?");

        $stmt->bindParam(1,$email);

        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }
        $password_hash = $results[0]['password_hash'];
        //$stmt->bind_result($password_hash);

        //$stmt->store_result();

        if (!$stmt->fetch()) {
            // Utilisateur trouvé avec l'e-mail
            // Maintenant, vérifier le mot de passe

            $stmt->fetch();

            $stmt = null;

            if (PassHash::check_password($password_hash, $password)) {
                // Mot de passe utilisateur est correcte
                return TRUE;
            } else {
                // mot de passe utilisateur est incorrect
                return FALSE;
            }
        } else {
            $stmt = null;

            // utilisateur n'existe pas avec l'e-mail
            return FALSE;
        }
    }

    /**
     * Vérification de l'utilisateur en double par adresse e-mail
     * @param String $email email à vérifier dans la db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id from api_users WHERE email = ?");
        $stmt->bindParam("s", $email);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt = null;
        return $num_rows > 0;
    }

    /**
     *Obtention de l'utilisateur par email
     * @param String $email
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM api_users WHERE email = ?");
        $stmt->bindParam(1, $email);
        if ($stmt->execute()) {
            $results = $stmt->fetchAll();
            $results = $results[0];
            $user = array();
            $user["name"] =$results['name'];
            $user["email"] = $results['email'];
            $user["api_key"] = $results['api_key'];
            $user["status"] = $results['status'];
            $user["created_at"] = $results['$created_at'];
            $stmt = null;
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Obtention de la clé API de l'utilisateur
     * @param String $user_id clé primaire de l'utilisateur
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM api_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($api_key);
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Obtention de l'identifiant de l'utilisateur par clé API
     * @param String $api_key
     */
    public function getUserId($api_key) {

        $stmt = $this->conn->prepare("SELECT id FROM api_users WHERE api_key = ?");
        $stmt->bindParam(1, $api_key);
        if ($stmt->execute()) {
            $user_id = $stmt->fetch()['id'];

            $stmt = null;
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validation de la clé API de l'utilisateur
     * Si la clé API est là dans db, elle est une clé valide
     * @param String $api_key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from api_users WHERE api_key = ?");
        $stmt->bindParam(1, $api_key);
        $stmt->execute();
        $stmt->fetchAll();
        $isExist = !$stmt->fetch();
        $stmt = null;
        return $isExist;
    }

    /**
     * Génération aléatoire unique MD5 String pour utilisateur clé Api
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /* ------------- méthodes table`notes` ------------------ */

    /**
     * Creation nouvelle note
     * @param String $user_id id de l'utilisateur à qui la tâche appartient
     * @param String $task texte de la tache
     */
    public function createNote($user_id, $title, $text) {
        $stmt = $this->conn->prepare("INSERT INTO api_notes(title,text) VALUES(?,?)");
        $stmt->bindParam(1, $title);
        $stmt->bindParam(2, $text);
        $result = $stmt->execute();
        $stmt = null;

        if ($result) {
            // ligne de tâche créé
            // maintenant assigner la tâche à l'utilisateur
            $new_note_id = $this->conn->lastInsertId();
            $res = $this->createUserNote($user_id, $new_note_id);
            $this->insertPositionNoteCreate($new_note_id);
            if ($res) {
                // tâche créée avec succès
                return $new_note_id;
            } else {
                //tâche n'a pas pu être créé
                return NULL;
            }
        } else {
            //tâche n'a pas pu être créé
            return NULL;
        }
    }

    /**
     * Obtention d'une seule tâche
     * @param String $task_id id de la tâche
     */
    public function getNotes($note_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT n.id, n.title, n.text, n.status, n.created_at, n.modified_at from api_notes n, api_user_notes un WHERE n.id = ? AND un.note_id = n.id AND un.user_id = ?");
        $stmt->bindParam(1, $note_id);
        $stmt->bindParam(2, $user_id);
        if ($stmt->execute()) {
            $res = array();
            $result = $stmt->fetch();
            $res["id"] = $result['id'];
            $res["title"] = $result['title'];
            $res["text"] = $result['text'];
            $res["status"] = $result['status'];
            $res["created_at"] = $result['created_at'];
            $res["modified_at"] = $result['modified_at'];
            $stmt = null;
            return $res;
        } else {
            return NULL;
        }
    }

    /**
     *Obtention de  tous les  tâches de l'utilisateur
     * @param String $user_id id de l'utilisateur
     */
    public function getAllUserNotes($user_id) {
        $stmt = $this->conn->prepare("SELECT n.* FROM api_notes n, api_user_notes un WHERE n.id = un.note_id AND un.user_id = ?");
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $notes = $stmt->fetchAll();
        $stmt = null;
        return $notes;
    }

    /**
     * Mise à jour de la tâche
     * @param String $task_id id de la tâche
     * @param String $task Le texte de la tâche
     * @param String $status le statut de la tâche
     */
    public function updateNote($user_id, $note_id, $title, $text, $status) {
        $stmt = $this->conn->prepare("UPDATE api_notes n, api_user_notes un set n.title = ?, n.text = ?, n.status = ? WHERE n.id = ? AND n.id = un.note_id AND un.user_id = ?");
        $stmt->bindParam(1, $title);
        $stmt->bindParam(2, $text);
        $stmt->bindParam(3, $status);
        $stmt->bindParam(4, $note_id);
        $stmt->bindParam(5, $user_id);

        $result = $stmt->execute();
        $stmt = null;
        return $result;
    }

    /**
     * Suppression d'une tâche
     * @param String $task_id id de la tâche à supprimer
     */
    public function deleteNote($user_id, $note_id) {
        $stmt = $this->conn->prepare("DELETE n FROM api_notes n, api_user_notes un WHERE n.id = ? AND un.note_id = n.id AND un.user_id = ?");
        $stmt->bindParam(1, $note_id);
        $stmt->bindParam(2, $user_id);
        $result = $stmt->execute();
        $stmt = null;
        return $result;
    }

    /* ------------- méthode de la table`user_tasks` ------------------ */

    /**
     * Fonction d'assigner une tâche à l'utilisateur
     * @param String $user_id id de l'utilisateur
     * @param String $task_id id de la tâche
     */
    public function createUserNote($user_id, $note_id) {
        $stmt = $this->conn->prepare("INSERT INTO api_user_notes(user_id, note_id) values(?, ?)");
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $note_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->errorInfo()[2]));
        }
        $stmt = null;
        return $result;
    }

    /**
     * Gestion de la position des notes
     * @param Array $note à modifier leur position
     */
    public function positionNote($noteArray,$user_id) {

        $stmt = $this->conn->prepare("UPDATE api_notes n, api_user_notes un set n.positionList = ? WHERE n.id = ? AND un.user_id = ?");

            $stmt->bindParam(1,$noteArray[0]['positionList']);
            $stmt->bindParam(2,$noteArray[0]['id']);
            $stmt->bindParam(3,$user_id);
            $result = $stmt->execute();

        return true;

        /*try {

            $stmt = $this->conn->prepare("UPDATE api_notes n, api_user_notes un set n.positionList = ? WHERE n.id = ? AND un.user_id = ?");

            foreach ($noteArray as $note) {
                $stmt->bindParam(1,$note['positionList']);
                $stmt->bindParam(2,$note['id']);
                $stmt->bindParam(3,$user_id);
                $stmt->execute();
            }

            $result = $this->conn->commit();
            return $result;

        }
        catch(PDOException $e) {
            echo "I'm sorry, but there was an error updating the database.";
            file_put_contents('PDOErrors.txt', $e->getMessage(), FILE_APPEND);

            $result = $this->conn->rollBack();
            return $result;
        }*/

    }

    /**
     * insert id position in create note.
     * @param id $noteId à modifier leur position
     */
    public function insertPositionNoteCreate($noteId) {
        $stmt = $this->conn->prepare("UPDATE api_notes n set n.positionList = ? WHERE n.id = ?");
        $stmt->bindParam(1, $noteId);
        $stmt->bindParam(2, $noteId);
        $result = $stmt->execute();
        $stmt = null;
        return $result;
    }
}

