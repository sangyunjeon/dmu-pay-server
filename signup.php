<?php
// 에러 출력 설정
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS & JSON 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');
session_start();

// DB 연결
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dmu_pay';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

$conn->begin_transaction();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username']);
    $password = $input['password'];
    $name     = trim($input['name']);
    $role     = 'student';
    $student_number = trim($input['student_number']);
    $major = trim($input['major']);

    // 필수 항목 확인
    if (!$username || !$password || !$name || !$student_number || !$major) {
        throw new Exception('모든 항목을 입력해주세요.');
    }

    // 1. 사전 등록된 학생 정보와 일치하는지 확인
    $stmt = $conn->prepare("SELECT * FROM student_info WHERE name = ? AND student_number = ? AND major = ?");
    $stmt->bind_param("sss", $name, $student_number, $major);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        throw new Exception('등록되지 않은 학생 정보입니다. 이름, 학번, 전공을 다시 확인하세요.');
    }
    $stmt->close();

    // 2. 아이디 중복 확인
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        throw new Exception('이미 존재하는 아이디입니다.');
    }
    $stmt->close();

    // 3. 학번으로 중복 가입 확인
    $stmt = $conn->prepare("SELECT user_id FROM student_detail WHERE student_number = ?");
    $stmt->bind_param("s", $student_number);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        throw new Exception('이미 가입된 회원입니다.');
    }
    $stmt->close();

    // 4. users 테이블에 가입 정보 등록
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hashedPassword, $name, $role);
    if (!$stmt->execute()) {
        throw new Exception('회원정보 등록 실패: ' . $stmt->error);
    }
    $user_id = $stmt->insert_id;
    $stmt->close();

    // ✅ 5. student_detail 테이블에 name 포함하여 저장
    $stmt = $conn->prepare("INSERT INTO student_detail (user_id, name, student_number, major, point) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("isss", $user_id, $name, $student_number, $major);
    if (!$stmt->execute()) {
        throw new Exception('학생 상세정보 등록 실패: ' . $stmt->error);
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
