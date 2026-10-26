SET foreign_key_checks = 0;

DROP TABLE IF EXISTS event_user;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    encrypted_password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'participant') NOT NULL DEFAULT 'participant',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- Evento (RF04): CRUD completo da Entrega 4.2.
-- Vagas restantes = capacity - COUNT(*) em event_user; calculadas, não armazenadas.
CREATE TABLE events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    kind        ENUM('lecture', 'workshop', 'short_course', 'academic_week') NOT NULL,
    modality    ENUM('in_person', 'online', 'hybrid') NOT NULL DEFAULT 'in_person',
    audience    SET('students', 'professors', 'staff', 'community') NOT NULL,
    starts_on   DATE NOT NULL,
    location    VARCHAR(255) NOT NULL,
    capacity    INT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT check_events_capacity CHECK (capacity >= 1),
    CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX index_events_on_starts_on (starts_on)
);

-- Inscrição: pivô NxN entre events e users (RF05, RF06; Entrega 4.4 com Ajax).
-- Índice único: uma Inscrição por Participante por Evento (RF06).
-- ON DELETE RESTRICT em event_id: Evento com Inscrições não pode ser removido (P03).
CREATE TABLE event_user (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT index_event_user_on_event_id_and_user_id UNIQUE (event_id, user_id),
    CONSTRAINT fk_event_user_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_event_user_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

SET foreign_key_checks = 1;
