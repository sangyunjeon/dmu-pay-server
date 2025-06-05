<?php
// CORS 설정 (Netlify 주소로 수정)
header('Access-Control-Allow-Origin: https://dmu-pay.netlify.app'); 
header('Access-Control-Allow-Credentials: true'); 
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

session_start();

// 닷홈용 DB 접속 정보
$dbHost = 'localhost';
$dbUser = 'dmupay01';
$dbPass = 'tkddbs0130!';
$dbName = 'dmupay01';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => '데이터베이스 연결에 실패했습니다.'
    ]);
    exit;
}

// 사용자 입력 받기
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => '아이디와 비밀번호를 모두 입력해주세요.'
    ]);
    exit;
}

// users 테이블에서 사용자 조회
$stmt = $conn->prepare("
    SELECT id, password, role, name
    FROM users
    WHERE username = ?
");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        'success' => false,
        'message' => '존재하지 않는 아이디입니다.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$storedHash = $row['password'];
$role       = $row['role'];
$name       = $row['name'];
$userId     = $row['id'];

$stmt->close();

// 비밀번호 검증 (SHA-256이 아니라 password_hash를 사용하는 경우)
if (!password_verify($password, $storedHash)) {
    echo json_encode([
        'success' => false,
        'message' => '비밀번호가 일치하지 않습니다.'
    ]);
    $conn->close();
    exit;
}

// 로그인 성공 → 세션 저장
$_SESSION['user'] = [
    'id'       => $userId,
    'username' => $username,
    'role'     => $role,
    'name'     => $name
];

// 성공 응답 반환
echo json_encode([
    'success' => true,
    'message' => '로그인 성공',
    'role' => $role
]);

$conn->close();
?>
