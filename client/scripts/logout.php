<?php
session_start();
session_destroy();
header('Location: /gccb/Home/login.php');