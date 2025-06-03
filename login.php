<?php


// login.php 맨 위에 추가
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

// login.php
header('Content-Type: application/json; charset=UTF-8');
session_start();

// 1) DB 접속 정보(자신 환경에 맞게 수정)
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';            // MySQL 비밀번호
$dbName = 'dmu_pay';     // 실제 사용 중인 DB 이름

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => '데이터베이스 연결에 실패했습니다.'
    ]);
    exit;
}

// 2) POST로 전달된 username, password 받기
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => '아이디와 비밀번호를 모두 입력해주세요.'
    ]);
    exit;
}

// 3) 입력받은 비밀번호를 SHA-256 해시로 변환 (DB에 SHA2('qwer123',256) 방식으로 저장했으므로)
$hashedInput = hash('sha256', $password);

// 4) users 테이블에서 해당 username에 대한 정보 조회
$stmt = $conn->prepare("
    SELECT id, password, role, name
      FROM users
     WHERE username = ?
");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

// 5) 결과가 없으면 “존재하지 않는 아이디” 반환
if ($result->num_rows !== 1) {
    echo json_encode([
        'success' => false,
        'message' => '존재하지 않는 아이디입니다.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// 6) 해당 행에서 해시된 비밀번호, 역할, 이름 가져오기
$row = $result->fetch_assoc();
$storedHash = $row['password'];
$role       = $row['role'];
$name       = $row['name'];
$userId     = $row['id'];

$stmt->close();

// 7) 비밀번호 검증 (변경된 부분!)
if (!password_verify($password, $storedHash)) {
    echo json_encode([
        'success' => false,
        'message' => '비밀번호가 일치하지 않습니다.'
    ]);
    $conn->close();
    exit;
}

// 8) 로그인 성공 → 세션에 사용자 정보 저장
$_SESSION['user'] = [
    'id'       => $userId,
    'username' => $username,
    'role'     => $role,
    'name'     => $name
];

echo json_encode([
    'success' => true,
    'message' => '로그인 성공',
    'role' => $role // 🔥 역할 정보 추가!
]);

$conn->close();
