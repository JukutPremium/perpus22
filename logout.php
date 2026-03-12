<?php
session_start();
session_destroy();
header('Location: /perpustakaan/index.php?msg=logout');
exit;
