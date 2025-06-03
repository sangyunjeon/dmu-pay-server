<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

$conn = new mysqli("localhost", "root", "", "dmu_pay");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB 연결 실패"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// 입력값 받기
$name = trim($data["name"]);
$student_number = trim($data["student_number"]);
$major = trim($data["major"]);
$username = trim($data["username"]);
$password = $data["password"];

// 1. 모든 필드가 비어 있으면 거절
if (!$name || !$student_number || !$major || !$username || !$password) {
    echo json_encode(["success" => false, "message" => "모든 항목을 입력해주세요."]);
    exit;
}

// 2. student_info에 해당 학생이 있는지 확인
$sql = "SELECT * FROM student_info WHERE student_number = ? AND name = ? AND major = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $student_number, $name, $major);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "입력한 학생 정보가 등록되어 있지 않습니다."]);
    exit;
}

// 3. student_detail 테이블에 이미 학번이 있는지 확인 (중복 가입 방지)
$sql = "SELECT * FROM student_detail WHERE student_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_number);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "이미 이 학번으로 가입된 계정이 존재합니다."]);
    exit;
}

// 4. users 테이블에 동일한 아이디가 있는지 확인
$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "이미 사용 중인 아이디입니다."]);
    exit;
}

// 5. users 테이블에 회원 정보 삽입
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, role, name) VALUES (?, ?, 'student', ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $hashed_password, $name);
$stmt->execute();
$user_id = $conn->insert_id;

// 6. student_detail 테이블에 학번/전공 삽입
$sql = "INSERT INTO student_detail (user_id, student_number, major, point) VALUES (?, ?, ?, 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $student_number, $major);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "학생 상세 정보 삽입 실패: " . $stmt->error]);
    exit;
}

echo json_encode(["success" => true, "message" => "회원가입이 완료되었습니다."]);
