<?php
session_start();
unset($_SESSION['generated_schedule']);
http_response_code(200);
