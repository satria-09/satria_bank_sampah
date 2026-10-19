CREATE DATABASE IF NOT EXISTS satria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE satria;
DROP TABLE IF EXISTS transaksi; DROP TABLE IF EXISTS pengepul; DROP TABLE IF EXISTS sampah; DROP TABLE IF EXISTS user;

CREATE TABLE user (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','nasabah','pengepul') NOT NULL DEFAULT 'nasabah',
  kategori ENUM('murid','guru') NULL DEFAULT NULL,
  saldo DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE sampah (
  id_sampah INT AUTO_INCREMENT PRIMARY KEY,
  jenis_sampah VARCHAR(100) NOT NULL,
  harga_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  stok_kg DECIMAL(10,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE pengepul (
  id_pengepul INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL UNIQUE,
  nama_pengepul VARCHAR(100) NOT NULL,
  alamat TEXT,
  no_hp VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pengepul_user FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transaksi (
  id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  id_sampah INT NULL,
  id_pengepul INT NULL,
  tipe ENUM('setor','jual','tarik','topup') NOT NULL DEFAULT 'setor',
  berat_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_rp DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  tanggal DATE NOT NULL,
  CONSTRAINT fk_t_user FOREIGN KEY (id_user) REFERENCES user(id_user) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_t_sampah FOREIGN KEY (id_sampah) REFERENCES sampah(id_sampah) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_t_pengepul FOREIGN KEY (id_pengepul) REFERENCES pengepul(id_pengepul) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO user (nama,username,password,role,saldo) VALUES
('Administrator','admin','$2y$10$KMLnyUWV6UnsXB9Gyg8c1urtuIqOiom7.AxKD/4UF0jPnk6YmuuO.','admin',500000),
('Aulia Rahmawati','aul','$2y$10$KMLnyUWV6UnsXB9Gyg8c1urtuIqOiom7.AxKD/4UF0jPnk6YmuuO.','nasabah',22500),
('Pengguna Umum','user','$2y$10$KMLnyUWV6UnsXB9Gyg8c1urtuIqOiom7.AxKD/4UF0jPnk6YmuuO.','nasabah',0),
('Pengepul Jaya','pengepul','$2y$10$KMLnyUWV6UnsXB9Gyg8c1urtuIqOiom7.AxKD/4UF0jPnk6YmuuO.','pengepul',970000);

INSERT INTO sampah (jenis_sampah,harga_per_kg,stok_kg) VALUES ('Botol Plastik',3000,25),('Kardus',2500,40),('Kertas',2000,30);
INSERT INTO pengepul (id_user,nama_pengepul,alamat,no_hp) VALUES (4,'Pengepul Jaya','Magetan','081234567890');
