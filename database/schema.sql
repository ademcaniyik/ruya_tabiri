-- Rüya Tabiri AI - MySQL 8 şeması (utf8mb4)
-- Kullanım: mysql -u root -p ruyatabiri < database/schema.sql

CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  userId      VARCHAR(64)  NOT NULL,
  name        VARCHAR(255) NULL,
  email       VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_userId (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kullanıcı başına tek satır: kalan kredi. created_at = bakiye bitince başlayan 24 saatlik yenilenme sayacı
CREATE TABLE IF NOT EXISTS tokens (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  userId      VARCHAR(64)  NOT NULL,
  token       INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tokens_userId (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_tokens (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,           -- users.id
  device_token  VARCHAR(512) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_device_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dream_history (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         VARCHAR(64)  NOT NULL,         -- users.userId
  dream           TEXT         NOT NULL,
  interpretation  MEDIUMTEXT   NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dream_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Satın alma kayıtları: tx_key UNIQUE olduğu için aynı işlem ikinci kez kredi vermez
CREATE TABLE IF NOT EXISTS iap_transactions (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tx_key      VARCHAR(128) NOT NULL,             -- android:<sha256(purchaseToken)> | ios:<transactionId>
  user_id     VARCHAR(64)  NOT NULL,
  platform    VARCHAR(10)  NOT NULL,
  product_id  VARCHAR(128) NOT NULL,
  order_id    VARCHAR(128) NULL,
  credits     INT          NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_iap_tx (tx_key),
  KEY idx_iap_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
