<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'manager') {
    header('Location: /login');
    exit;
}

$html = $_POST['html'] ?? '';

if (!$html) {
    die("Нет данных для отчета.");
}

// Создаём PDF из переданного HTML
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("financial_report", ["Attachment" => false]);
exit;
