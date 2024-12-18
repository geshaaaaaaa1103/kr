<?php 
header('Content-Type: application/json'); 

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'php_study'); 
define('DB_USER', 'root'); 
define('DB_PASS', 'mypass'); 

class Database {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->sendResponse(500, 'Ошибка подключения к базе данных: ' . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function sendResponse($statusCode, $message, $data = null) {
        http_response_code($statusCode);
        echo json_encode(array_merge(['message' => $message], $data ? ['data' => $data] : []));
        exit;
    }
}

class TaskManager {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = trim($_SERVER['REQUEST_URI'], '/');
        $segments = explode('/', $uri);
        $resource = $segments[0] ?? '';
        $id = $segments[1] ?? null;

        if ($resource !== 'tasks') {
            $this->db->sendResponse(404, 'Ресурс не найден');
        }

        switch ($method) {
            case 'GET':
                $this->getAllTasks($id);
                break;
            case 'POST':
                $this->createTask();
                break;
            case 'PUT':
                $this->updateTask($id);
                break;
            case 'DELETE':
                $this->deleteTask($id);
                break;
            default:
                $this->db->sendResponse(405, 'Метод не поддерживается');
        }
    }

    private function getAllTasks($id) {
        if ($id) {
            $this->getTaskById($id);
        } else {
            $filter = $_GET['filter'] ?? 'all';
            $tasks = $this->fetchTasks($filter);
            $this->db->sendResponse(200, 'Задачи успешно получены', $tasks);
        }
    }

    private function fetchTasks($filter) {
        $query = "SELECT id, title, completed FROM tasks";
        if ($filter === 'completed') {
            $query .= " WHERE completed = 1";
        } elseif ($filter === 'active') {
            $query .= " WHERE completed = 0";
        }
        $query .= " ORDER BY id ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTaskById($id) {
        $stmt = $this->db->prepare("SELECT id, title, completed FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $this->db->sendResponse(404, 'Задача не найдена');
        } else {
            $this->db->sendResponse(200, 'Задача успешно получена', $task);
        }
    }

    private function createTask() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['title'])) {
            $this->db->sendResponse(400, 'Название задачи обязательно');
        }

        $stmt = $this->db->prepare("INSERT INTO tasks (title) VALUES (:title)");
        $stmt->execute([':title' => trim($data['title'])]);
        $this->db->sendResponse(201, 'Задача успешно создана', [
            'id' => $this->db->lastInsertId(),
            'title' => trim($data['title']),
            'completed' => false
        ]);
    }

    private function updateTask($id) {
        if (!$id) {
            $this->db->sendResponse(400, 'ID задачи обязателен для обновления');
        }

        $data = json_decode(file_get_contents('php
://input'), true);
        $stmt = $this->db->prepare("SELECT id, title, completed FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $this->db->sendResponse(404, 'Задача не найдена');
        }

        $newTitle = $data['title'] ?? $task['title'];
        $newCompleted = isset($data['completed']) ? (int)$data['completed'] : $task['completed'];

        $updateStmt = $this->db->prepare("UPDATE tasks SET title = :title, completed = :completed WHERE id = :id");
        $updateStmt->execute([
            ':title' => trim($newTitle),
            ':completed' => $newCompleted,
            ':id' => $id
        ]);

        $this->db->sendResponse(200, 'Задача успешно обновлена', [
            'id' => $id,
            'title' => trim($newTitle),
            'completed' => $newCompleted
        ]);
    }

    private function deleteTask($id) {
        if (!$id) {
            $this->db->sendResponse(400, 'ID задачи обязателен для удаления');
        }

        $stmt = $this->db->prepare("SELECT id FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->db->sendResponse(404, 'Задача не найдена');
        }

        $deleteStmt = $this->db->prepare("DELETE FROM tasks WHERE id = :id");
        $deleteStmt->execute([':id' => $id]);
        $this->db->sendResponse(204, 'Задача успешно удалена');
    }
}

$db = new Database();
$taskManager = new TaskManager($db);
$taskManager->handleRequest();
?>
