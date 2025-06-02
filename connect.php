<?php
$host = "db4free.net"; // 또는 사용하는 외부 DB 주소
$user = "your_db_user";
$pw = "your_db_password";
$dbName = "your_db_name";

$conn = mysqli_connect($host, $user, $pw, $dbName);

if (!$conn) {
    die("DB 연결 실패: " . mysqli_connect_error());
}

// 성공 시 아무 메시지도 출력하지 않음
?>
