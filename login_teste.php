<?php
session_start();
$_SESSION['usuario_id'] = 1;
header('Location: garagem.php');