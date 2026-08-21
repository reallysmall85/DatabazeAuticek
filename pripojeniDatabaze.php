<?php


  define("SQL_HOST","localhost");
  define("SQL_DBNAME","autickariumDB");
  define("SQL_USERNAME","autickariumDB");
  define("SQL_PASSWORD","Night4ce5!");


  $connection = mysqli_connect(
    SQL_HOST,
    SQL_USERNAME,
    SQL_PASSWORD,
    SQL_DBNAME
);

if (!$connection) {
    die("Chyba připojení k databázi: " . mysqli_connect_error());
}

if (!mysqli_set_charset($connection, "utf8mb4")) {
    die("Chyba při nastavení UTF-8: " . mysqli_error($connection));
}

?>
