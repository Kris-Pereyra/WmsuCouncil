<?php
date_default_timezone_set('Asia/Manila');

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'student_council_funds';
const DB_USER = 'root';
const DB_PASS = '';

const CURRENCY = '₱';                    

const ALLOWED_EMAIL_DOMAIN = '@wmsu.edu.ph';
const CODE_TTL_MINUTES     = 10;             

const MAIL_MODE = 'smtp';

const SMTP_HOST      = 'smtp.gmail.com';
const SMTP_PORT      = 587;
const SMTP_SECURE    = 'tls';          
define('SMTP_USER', getenv('SMTP_USER') ?: 'wmsucouncilfunds@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'hixgeuvzhdbeweza');
const SMTP_CA_FILE   = '';              
define('MAIL_FROM', getenv('MAIL_FROM') ?: SMTP_USER);
const MAIL_FROM_NAME = 'WMSU Student Council Funds';