-- TABLE ENTREPRISES
CREATE TABLE entreprises (
    id_entreprise INT AUTO_INCREMENT PRIMARY KEY,
    nom_entreprise VARCHAR(100) NOT NULL,
    adresse VARCHAR(255),
    email VARCHAR(100),
    telephone VARCHAR(20)
);

-- TABLE ROLES
CREATE TABLE roles (
    id_role INT AUTO_INCREMENT PRIMARY KEY,
    nom_role VARCHAR(50) NOT NULL
);

-- TABLE UTILISATEURS
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    mail VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role INT,
    id_entreprise INT,
    FOREIGN KEY (role) REFERENCES roles(id_role),
    FOREIGN KEY (id_entreprise) REFERENCES entreprises(id_entreprise)
);

-- TABLE SERVICES
CREATE TABLE services (
    id_service INT AUTO_INCREMENT PRIMARY KEY,
    nom_service VARCHAR(100)
);

-- TABLE CATEGORIES
CREATE TABLE categories (
    id_cat INT AUTO_INCREMENT PRIMARY KEY,
    nom_cat VARCHAR(100)
);

-- TABLE DOCUMENTS
CREATE TABLE documents (
    id_doc INT AUTO_INCREMENT PRIMARY KEY,
    id_cat INT,
    id_service INT,
    nom_client VARCHAR(100),
    numero_dossier VARCHAR(100),
    annee_audience YEAR,
    description TEXT,
    statut VARCHAR(50),
    url_doc VARCHAR(255),
    type_doc VARCHAR(100),
    autres TEXT,
    FOREIGN KEY (id_cat) REFERENCES categories(id_cat),
    FOREIGN KEY (id_service) REFERENCES services(id_service)
);

-- TABLE MOUVEMENTS
CREATE TABLE mouvements (
    id_mouv INT AUTO_INCREMENT PRIMARY KEY,
    id_doc INT,
    id_user INT,
    action VARCHAR(100),
    date_heure DATETIME,
    FOREIGN KEY (id_doc) REFERENCES documents(id_doc),
    FOREIGN KEY (id_user) REFERENCES users(id_user)
);
