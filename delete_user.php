<?php
session_start();
include 'includes/db.php';

// Only admin can delete
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM `user` WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: users.php?msg=deleted");
        exit();
    } else {
        die("Failed to delete user: " . $conn->error);
    }
} else {
    header("Location: users.php");
    exit();
}
?>