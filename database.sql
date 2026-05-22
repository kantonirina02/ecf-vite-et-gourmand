-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 21 mai 2026 à 09:41
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `vite_et_gourmand`
--

-- --------------------------------------------------------

--
-- Structure de la table `allergene`
--

DROP TABLE IF EXISTS `allergene`;
CREATE TABLE IF NOT EXISTS `allergene` (
  `id_allergene` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_allergene`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `allergene`
--

INSERT INTO `allergene` (`id_allergene`, `nom`) VALUES
(1, 'Gluten'),
(2, 'Lait'),
(3, 'Oeufs'),
(4, 'Fruits à coque'),
(5, 'Moutarde'),
(6, 'Arachides'),
(7, 'Soja'),
(8, 'Poisson'),
(9, 'Crustacés'),
(10, 'Sulfites');

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE IF NOT EXISTS `avis` (
  `id_avis` int NOT NULL AUTO_INCREMENT,
  `note` int DEFAULT NULL,
  `commentaire` text,
  `statut` varchar(20) DEFAULT 'en attente',
  `id_utilisateur` int DEFAULT NULL,
  `id_commande` int DEFAULT NULL,
  PRIMARY KEY (`id_avis`),
  KEY `id_utilisateur` (`id_utilisateur`),
  KEY `id_commande` (`id_commande`)
) ;

--
-- Déchargement des données de la table `avis`
--

INSERT INTO `avis` (`id_avis`, `note`, `commentaire`, `statut`, `id_utilisateur`, `id_commande`) VALUES
(10, 5, 'Une prestation exceptionnelle pour notre mariage ! Le Menu Prestige a fait l\'unanimité parmi nos invités. L\'équipe est professionnelle, discrète et à l\'écoute. Les produits sont d\'une qualité rare. Un immense merci à Julie et José !', 'validé', 9, 4),
(11, 4, 'Très belle expérience pour notre repas de fin d\'année d\'entreprise. Les produits sont frais, locaux et la présentation est vraiment très élégante. Un service irréprochable. Nous referons appel à Vite & Gourmand sans hésiter.', 'validé', 9, 4),
(12, 5, 'Le Menu commandé pour les 50 ans de mon mari était tout simplement divin. Les cuissons étaient parfaites et mention spéciale pour le dessert qui était un véritable chef-d\'œuvre visuel et gustatif. Bravo au chef !', 'validé', 9, 4);

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

DROP TABLE IF EXISTS `commande`;
CREATE TABLE IF NOT EXISTS `commande` (
  `id_commande` int NOT NULL AUTO_INCREMENT,
  `date_prestation` date NOT NULL,
  `heure_prestation` time NOT NULL,
  `lieu_prestation` text NOT NULL,
  `nb_personnes` int NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `statut` varchar(50) DEFAULT 'en attente',
  `id_utilisateur` int DEFAULT NULL,
  `id_menu` int DEFAULT NULL,
  PRIMARY KEY (`id_commande`),
  KEY `id_utilisateur` (`id_utilisateur`),
  KEY `id_menu` (`id_menu`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`id_commande`, `date_prestation`, `heure_prestation`, `lieu_prestation`, `nb_personnes`, `prix_total`, `statut`, `id_utilisateur`, `id_menu`) VALUES
(4, '2026-05-20', '11:30:00', '6 rue deodora 31000 toulouse', 10, 870.16, 'terminée', 9, 1);

-- --------------------------------------------------------

--
-- Structure de la table `commande_statut_historique`
--

DROP TABLE IF EXISTS `commande_statut_historique`;
CREATE TABLE IF NOT EXISTS `commande_statut_historique` (
  `id_historique` int NOT NULL AUTO_INCREMENT,
  `id_commande` int NOT NULL,
  `statut` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historique`),
  KEY `idx_commande_historique` (`id_commande`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `email_log`
--

DROP TABLE IF EXISTS `email_log`;
CREATE TABLE IF NOT EXISTS `email_log` (
  `id_email_log` int NOT NULL AUTO_INCREMENT,
  `destinataire` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_email_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `horaire`
--

DROP TABLE IF EXISTS `horaire`;
CREATE TABLE IF NOT EXISTS `horaire` (
  `id_horaire` int NOT NULL AUTO_INCREMENT,
  `jour` varchar(50) NOT NULL,
  `heures` varchar(50) NOT NULL,
  PRIMARY KEY (`id_horaire`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `horaire`
--

INSERT INTO `horaire` (`id_horaire`, `jour`, `heures`) VALUES
(1, 'Lundi', '08:00 - 19:00'),
(2, 'Mardi', '08:00 - 19:00'),
(3, 'Mercredi', '08:00 - 19:00'),
(4, 'Jeudi', '08:00 - 19:00'),
(5, 'Vendredi', '08:00 - 19:00'),
(6, 'Samedi', '09:00 - 18:00'),
(7, 'Dimanche', '09:00 - 14:00');

-- --------------------------------------------------------

--
-- Structure de la table `login_attempt`
--

DROP TABLE IF EXISTS `login_attempt`;
CREATE TABLE IF NOT EXISTS `login_attempt` (
  `id_attempt` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_attempt`),
  UNIQUE KEY `uniq_login_attempt` (`email`,`ip_address`),
  KEY `idx_login_attempt_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `menu`
--

DROP TABLE IF EXISTS `menu`;
CREATE TABLE IF NOT EXISTS `menu` (
  `id_menu` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(100) NOT NULL,
  `description` text,
  `image` varchar(255) NOT NULL,
  `theme` varchar(50) DEFAULT NULL,
  `regime` varchar(50) DEFAULT NULL,
  `nb_personnes_min` int NOT NULL,
  `prix_min` decimal(10,2) NOT NULL,
  `stock` int DEFAULT '0',
  `conditions` text,
  PRIMARY KEY (`id_menu`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `menu`
--

INSERT INTO `menu` (`id_menu`, `titre`, `description`, `image`, `theme`, `regime`, `nb_personnes_min`, `prix_min`, `stock`, `conditions`) VALUES
(1, 'Menu Prestige de Noël', 'Un menu d\'exception pour célébrer les fêtes avec vos proches. Découvrez l\'alliance parfaite entre tradition et modernité gastronomique.', 'menu1.png', 'Noel', 'classique', 10, 85.00, 15, 'Commande à passer au moins 7 jours à l\'avance. À conserver au frais (0-4°C).'),
(2, 'Saveurs Printanières', 'Célébrez l\'arrivée des beaux jours avec ce menu rafraîchissant, élaboré à partir de produits frais et locaux.', 'menu2.png', 'Pâques', 'classique', 6, 65.00, 20, 'Commande à passer au moins 5 jours à l\'avance.'),
(3, 'L\'Éveil Végétal', 'Un voyage culinaire 100% végétalien, riche en couleurs et en saveurs surprenantes.', 'menu3.avif', 'Classique', 'vegan', 4, 55.00, 30, 'Préparation minute conseillée.');

-- --------------------------------------------------------

--
-- Structure de la table `menu_image`
--

DROP TABLE IF EXISTS `menu_image`;
CREATE TABLE IF NOT EXISTS `menu_image` (
  `id_image` int NOT NULL AUTO_INCREMENT,
  `chemin` varchar(255) NOT NULL,
  `id_menu` int DEFAULT NULL,
  PRIMARY KEY (`id_image`),
  KEY `id_menu` (`id_menu`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `menu_plat`
--

DROP TABLE IF EXISTS `menu_plat`;
CREATE TABLE IF NOT EXISTS `menu_plat` (
  `id_menu` int NOT NULL,
  `id_plat` int NOT NULL,
  PRIMARY KEY (`id_menu`,`id_plat`),
  KEY `id_plat` (`id_plat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `menu_plat`
--

INSERT INTO `menu_plat` (`id_menu`, `id_plat`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 4),
(2, 5),
(2, 6),
(3, 7),
(3, 8),
(3, 9);

-- --------------------------------------------------------

--
-- Structure de la table `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
CREATE TABLE IF NOT EXISTS `password_reset` (
  `id_reset` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reset`),
  KEY `idx_password_reset_token` (`email`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plat`
--

DROP TABLE IF EXISTS `plat`;
CREATE TABLE IF NOT EXISTS `plat` (
  `id_plat` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `categorie` enum('entrée','plat','dessert') NOT NULL,
  PRIMARY KEY (`id_plat`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `plat`
--

INSERT INTO `plat` (`id_plat`, `nom`, `categorie`) VALUES
(1, 'Foie gras de canard mi-cuit, chutney de figues', 'entrée'),
(2, 'Chapon farci aux marrons, jus truffé', 'plat'),
(3, 'Bûche au chocolat Grand Cru et praliné', 'dessert'),
(4, 'Asperges blanches rôties, sauce mousseline', 'entrée'),
(5, 'Carré d\'agneau en croûte d\'herbes', 'plat'),
(6, 'Nid de Pâques revisité', 'dessert'),
(7, 'Velouté de petits pois à la menthe', 'entrée'),
(8, 'Risotto aux champignons sauvages et truffe', 'plat'),
(9, 'Tartelette aux fruits de saison, crème végétale', 'dessert');

-- --------------------------------------------------------

--
-- Structure de la table `plat_allergene`
--

DROP TABLE IF EXISTS `plat_allergene`;
CREATE TABLE IF NOT EXISTS `plat_allergene` (
  `id_plat` int NOT NULL,
  `id_allergene` int NOT NULL,
  PRIMARY KEY (`id_plat`,`id_allergene`),
  KEY `id_allergene` (`id_allergene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `plat_allergene`
--

INSERT INTO `plat_allergene` (`id_plat`, `id_allergene`) VALUES
(1, 1),
(5, 1),
(9, 1),
(3, 2),
(4, 2),
(6, 2),
(3, 3),
(4, 3),
(6, 3),
(3, 4),
(6, 4),
(5, 5);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gsm` varchar(20) DEFAULT NULL,
  `adresse_postale` text,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'utilisateur',
  `statut_compte` varchar(20) NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `nom`, `prenom`, `email`, `gsm`, `adresse_postale`, `mot_de_passe`, `role`, `statut_compte`) VALUES
(9, 'Jose', 'Jose', 'admin@viteetgourmand.fr', '0600000000', 'Bordeaux', '$2y$10$CcYBse4buMx5y9fYdVtR/uMe5GcB9w.w8Xrcqu0vSK5Hlj8cCCd2W', 'admin', 'actif');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`);

--
-- Contraintes pour la table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `commande_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`),
  ADD CONSTRAINT `commande_ibfk_2` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`);

--
-- Contraintes pour la table `commande_statut_historique`
--
ALTER TABLE `commande_statut_historique`
  ADD CONSTRAINT `fk_historique_commande` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON DELETE CASCADE;

--
-- Contraintes pour la table `menu_image`
--
ALTER TABLE `menu_image`
  ADD CONSTRAINT `menu_image_ibfk_1` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE;

--
-- Contraintes pour la table `menu_plat`
--
ALTER TABLE `menu_plat`
  ADD CONSTRAINT `menu_plat_ibfk_1` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_plat_ibfk_2` FOREIGN KEY (`id_plat`) REFERENCES `plat` (`id_plat`) ON DELETE CASCADE;

--
-- Contraintes pour la table `plat_allergene`
--
ALTER TABLE `plat_allergene`
  ADD CONSTRAINT `plat_allergene_ibfk_1` FOREIGN KEY (`id_plat`) REFERENCES `plat` (`id_plat`) ON DELETE CASCADE,
  ADD CONSTRAINT `plat_allergene_ibfk_2` FOREIGN KEY (`id_allergene`) REFERENCES `allergene` (`id_allergene`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
