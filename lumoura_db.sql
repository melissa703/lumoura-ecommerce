-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 20 jan. 2026 à 14:44
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `lumoura_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id_avis` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_utilisateur` int(11) NOT NULL,
  `note` int(11) DEFAULT NULL CHECK (`note` >= 1 and `note` <= 5),
  `commentaire` text DEFAULT NULL,
  `date_avis` datetime DEFAULT current_timestamp(),
  `statut` enum('en attente','approuve') DEFAULT 'en attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `avis`
--

INSERT INTO `avis` (`id_avis`, `id_produit`, `id_utilisateur`, `note`, `commentaire`, `date_avis`, `statut`) VALUES
(1, 1, 2, 5, 'Magnifique parfum ! La tenue est exceptionnelle, je le porte tous les jours.', '2026-01-20 14:19:30', 'approuve'),
(2, 1, 3, 4, 'Très bon parfum, emballage soigné. Livraison rapide.', '2026-01-20 14:19:30', 'approuve'),
(3, 6, 2, 5, 'Exactement ce que je cherchais. Notes boisées parfaites.', '2026-01-20 14:19:30', 'approuve'),
(4, 11, 3, 5, 'Parfum unisexe parfait. Mon partenaire et moi l\'adorons.', '2026-01-20 14:19:30', 'approuve'),
(5, 2, 4, 3, 'Agréable mais la tenue pourrait être meilleure.', '2026-01-20 14:19:30', 'approuve'),
(6, 7, 2, 4, 'Parfum masculin élégant. Parfait pour les occasions spéciales.', '2026-01-20 14:19:30', 'approuve'),
(7, 3, 3, 5, 'Fragrance mystérieuse et envoûtante. Je le recommande !', '2026-01-20 14:19:30', 'approuve'),
(8, 13, 4, 4, 'Parfum frais et exotique, parfait pour l\'été.', '2026-01-20 14:19:30', 'approuve'),
(9, 16, 2, 5, 'Excellent rapport qualité-prix. Une belle découverte.', '2026-01-20 14:19:30', 'approuve'),
(10, 8, 3, 4, 'Parfum moderne qui plaît à mon entourage.', '2026-01-20 14:19:30', 'approuve');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id_categorie` int(11) NOT NULL,
  `nom_categorie` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `ordre_affichage` int(11) DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id_categorie`, `nom_categorie`, `description`, `slug`, `ordre_affichage`, `parent_id`) VALUES
(1, 'Parfums Femme', 'Collection exclusive pour femme aux notes florales et fruitées', 'parfums-femme', 1, NULL),
(2, 'Parfums Homme', 'Collection masculine aux notes boisées et épicées', 'parfums-homme', 2, NULL),
(3, 'Parfums Unisexe', 'Fragrances qui conviennent à tous', 'parfums-unisexe', 3, NULL),
(4, 'Eau de Parfum', 'Concentration élevée, tenue longue durée', 'eau-de-parfum', 4, NULL),
(5, 'Eau de Toilette', 'Concentration moyenne, parfait pour la journée', 'eau-de-toilette', 5, NULL),
(6, 'Parfum', 'Concentration la plus élevée, essence pure', 'parfum', 6, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id_commande` int(11) NOT NULL,
  `numero_commande` varchar(20) NOT NULL,
  `id_utilisateur` int(11) NOT NULL,
  `date_commande` datetime DEFAULT current_timestamp(),
  `statut` enum('en attente','payee','expediee','livree','annulee') DEFAULT 'en attente',
  `montant` decimal(10,2) NOT NULL,
  `frais_livraison` decimal(6,2) DEFAULT 0.00,
  `mode_livraison` varchar(50) DEFAULT NULL,
  `mode_paiement` varchar(50) DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commandes`
--

INSERT INTO `commandes` (`id_commande`, `numero_commande`, `id_utilisateur`, `date_commande`, `statut`, `montant`, `frais_livraison`, `mode_livraison`, `mode_paiement`, `date_paiement`, `notes`) VALUES
(1, 'CMD-2024-001', 2, '2026-01-20 14:19:30', 'livree', 260.00, 0.00, 'Colissimo', 'Carte Bancaire', '2024-01-15 14:30:00', NULL),
(2, 'CMD-2024-002', 3, '2026-01-20 14:19:30', 'expediee', 115.00, 4.90, 'Chronopost', 'PayPal', '2024-02-10 10:15:00', NULL),
(3, 'CMD-2024-003', 2, '2026-01-20 14:19:30', 'payee', 450.00, 0.00, 'Colissimo', 'Carte Bancaire', '2024-02-20 16:45:00', NULL),
(4, 'CMD-2024-004', 4, '2026-01-20 14:19:30', 'en attente', 95.00, 4.90, 'Colissimo', 'Carte Bancaire', NULL, NULL),
(5, 'CMD-2024-005', 2, '2026-01-20 14:19:30', 'livree', 325.00, 0.00, 'Chronopost', 'PayPal', '2024-03-05 09:20:00', NULL),
(6, 'CMD-2024-006', 3, '2026-01-20 14:19:30', 'expediee', 210.00, 0.00, 'Colissimo', 'Carte Bancaire', '2024-03-12 14:15:00', NULL);

--
-- Déclencheurs `commandes`
--
DELIMITER $$
CREATE TRIGGER `before_order_insert` BEFORE INSERT ON `commandes` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    
    -- Récupérer le prochain numéro de commande
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_commande, 9) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM commandes 
    WHERE numero_commande LIKE CONCAT('CMD-', YEAR(NOW()), '-%');
    
    -- Générer le numéro de commande
    IF NEW.numero_commande IS NULL OR NEW.numero_commande = '' THEN
        SET NEW.numero_commande = CONCAT('CMD-', YEAR(NOW()), '-', LPAD(next_num, 3, '0'));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_status_update` BEFORE UPDATE ON `commandes` FOR EACH ROW BEGIN
    IF NEW.statut = 'payee' AND OLD.statut != 'payee' THEN
        SET NEW.date_paiement = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `details_commande`
--

CREATE TABLE `details_commande` (
  `id_detail` int(11) NOT NULL,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `details_commande`
--

INSERT INTO `details_commande` (`id_detail`, `id_commande`, `id_produit`, `quantite`, `prix_unitaire`) VALUES
(1, 1, 1, 2, 125.00),
(2, 1, 6, 1, 135.00),
(3, 2, 11, 1, 120.00),
(4, 3, 1, 1, 125.00),
(5, 3, 6, 2, 135.00),
(6, 3, 11, 1, 120.00),
(7, 4, 2, 1, 95.00),
(8, 5, 3, 2, 145.00),
(9, 5, 16, 1, 135.00),
(10, 6, 7, 1, 155.00),
(11, 6, 13, 1, 130.00);

--
-- Déclencheurs `details_commande`
--
DELIMITER $$
CREATE TRIGGER `after_order_detail_insert` AFTER INSERT ON `details_commande` FOR EACH ROW BEGIN
    UPDATE produits 
    SET stock = stock - NEW.quantite 
    WHERE id_produit = NEW.id_produit;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

CREATE TABLE `favoris` (
  `id_favori` int(11) NOT NULL,
  `id_utilisateur` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `favoris`
--

INSERT INTO `favoris` (`id_favori`, `id_utilisateur`, `id_produit`, `date_ajout`) VALUES
(1, 2, 1, '2026-01-20 14:19:30'),
(2, 2, 6, '2026-01-20 14:19:30'),
(3, 2, 11, '2026-01-20 14:19:30'),
(4, 3, 1, '2026-01-20 14:19:30'),
(5, 3, 7, '2026-01-20 14:19:30'),
(6, 4, 2, '2026-01-20 14:19:30'),
(7, 4, 13, '2026-01-20 14:19:30'),
(8, 2, 16, '2026-01-20 14:19:30'),
(9, 3, 9, '2026-01-20 14:19:30'),
(10, 4, 15, '2026-01-20 14:19:30');

-- --------------------------------------------------------

--
-- Structure de la table `newsletters`
--

CREATE TABLE `newsletters` (
  `id_newsletter` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `newsletters`
--

INSERT INTO `newsletters` (`id_newsletter`, `email`, `date_inscription`, `actif`) VALUES
(1, 'client1@email.com', '2026-01-20 14:19:30', 1),
(2, 'client2@email.com', '2026-01-20 14:19:30', 1),
(3, 'client3@email.com', '2026-01-20 14:19:30', 1),
(4, 'client4@email.com', '2026-01-20 14:19:30', 1),
(5, 'client5@email.com', '2026-01-20 14:19:30', 1);

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

CREATE TABLE `panier` (
  `id_panier` int(11) NOT NULL,
  `id_utilisateur` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id_panier`, `id_utilisateur`, `id_produit`, `quantite`, `date_ajout`) VALUES
(1, 2, 3, 1, '2026-01-20 14:19:30'),
(2, 2, 6, 2, '2026-01-20 14:19:30'),
(3, 3, 1, 1, '2026-01-20 14:19:30'),
(4, 4, 13, 1, '2026-01-20 14:19:30'),
(5, 2, 15, 3, '2026-01-20 14:19:30'),
(6, 3, 8, 1, '2026-01-20 14:19:30');

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id_produit` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `description_courte` varchar(200) DEFAULT NULL,
  `id_categorie` int(11) NOT NULL,
  `marque` varchar(50) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `volume_ml` int(11) NOT NULL,
  `concentration` enum('Eau Fraiche','Eau de Cologne','Eau de Toilette','Eau de Parfum','Parfum') DEFAULT NULL,
  `genre` enum('Homme','Femme','Unisexe') DEFAULT 'Unisexe',
  `date_ajout` datetime DEFAULT current_timestamp(),
  `bestseller` tinyint(1) DEFAULT 0,
  `nouveaute` tinyint(1) DEFAULT 0,
  `promotion_pourcentage` decimal(5,2) DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `seuil_alerte` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id_produit`, `reference`, `nom`, `description`, `description_courte`, `id_categorie`, `marque`, `prix`, `volume_ml`, `concentration`, `genre`, `date_ajout`, `bestseller`, `nouveaute`, `promotion_pourcentage`, `image_url`, `stock`, `seuil_alerte`) VALUES
(1, 'LUM-F-001', 'Rose Éternelle', 'Un parfum floral romantique aux notes de rose bulgare, jasmin et vanille de Madagascar. Une fragrance envoûtante qui évoque l\'élégance et la féminité.', 'Parfum floral romantique aux notes de rose et vanille', 1, 'Lumoura', 125.00, 100, 'Eau de Parfum', 'Femme', '2026-01-20 14:19:30', 1, 0, 0.00, 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 50, 10),
(2, 'LUM-F-002', 'Jardin Secret', 'Fragrance fraîche et légère aux notes d\'agrumes, de fleur d\'oranger et de musc blanc. Parfait pour les journées ensoleillées.', 'Parfum frais aux notes d\'agrumes', 1, 'Lumoura', 95.00, 50, 'Eau de Toilette', 'Femme', '2026-01-20 14:19:30', 0, 1, 20.00, 'https://images.unsplash.com/photo-1590736969956-6d9c2a8d6972?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 40, 10),
(3, 'LUM-F-003', 'Nuit d\'Orient', 'Fragrance mystérieuse aux notes d\'ambre, de patchouli et de fleur de tubéreuse. Une invitation au voyage nocturne.', 'Parfum oriental mystérieux', 1, 'Lumoura', 145.00, 100, 'Parfum', 'Femme', '2026-01-20 14:19:30', 1, 0, 15.00, 'https://images.unsplash.com/photo-1600566752355-35792bedcfea?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 30, 10),
(4, 'LUM-F-004', 'Fleur de Printemps', 'Fragrance printanière aux notes de pivoine, de cerisier et de miel. Douce et joyeuse, elle capture l\'essence du renouveau.', 'Parfum printanier floral', 1, 'Lumoura', 110.00, 100, 'Eau de Toilette', 'Femme', '2026-01-20 14:19:30', 0, 0, 0.00, 'https://images.unsplash.com/photo-1543858741-6c4c5c5c6c6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 98, 10),
(5, 'LUM-F-005', 'Ambre Précieux', 'Parfum oriental aux notes chaudes d\'ambre, de vanille et de santal. Luxueux et sensuel.', 'Parfum oriental chaud', 1, 'Lumoura', 160.00, 100, 'Eau de Parfum', 'Femme', '2026-01-20 14:19:30', 1, 0, 10.00, 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 0, 10),
(6, 'LUM-H-001', 'Bois Mystique', 'Fragrance boisée et sophistiquée aux notes de cèdre, vétiver et poivre noir. Pour l\'homme moderne et élégant.', 'Parfum boisé masculin', 2, 'Lumoura', 135.00, 100, 'Eau de Parfum', 'Homme', '2026-01-20 14:19:30', 1, 0, 10.00, 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 45, 10),
(7, 'LUM-H-002', 'Cuir Noble', 'Parfum audacieux aux notes de cuir, tabac et tonka. Évoque le luxe et la tradition.', 'Parfum cuir et tabac', 2, 'Lumoura', 155.00, 100, 'Eau de Parfum', 'Homme', '2026-01-20 14:19:30', 0, 1, 23.00, 'https://images.unsplash.com/photo-1590736969956-6d9c2a8d6972?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 35, 10),
(8, 'LUM-H-003', 'Aqua Marine', 'Fragrance fraîche et marine aux notes d\'agrumes, de lavande et de mousse de chêne. Idéal pour l\'été.', 'Parfum frais et marin', 2, 'Lumoura', 115.00, 100, 'Eau de Toilette', 'Homme', '2026-01-20 14:19:30', 1, 0, 25.00, 'https://images.unsplash.com/photo-1600566752355-35792bedcfea?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 64, 10),
(9, 'LUM-H-004', 'Urban Legend', 'Fragrance urbaine aux notes de béton humide, de cuir et d\'encens. Moderne et intrigante.', 'Parfum urbain moderne', 2, 'Lumoura', 140.00, 100, 'Eau de Parfum', 'Homme', '2026-01-20 14:19:30', 1, 0, 20.00, 'https://images.unsplash.com/photo-1543858741-6c4c5c5c6c6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 5, 10),
(10, 'LUM-H-005', 'Tobacco Royal', 'Parfum riche aux notes de tabac blond, de miel et de cuir vieilli. Pour l\'homme distingué.', 'Parfum tabac luxueux', 2, 'Lumoura', 165.00, 100, 'Parfum', 'Homme', '2026-01-20 14:19:30', 0, 1, 15.00, 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 25, 10),
(11, 'LUM-U-001', 'Nuit Étoilée', 'Fragrance universelle aux notes d\'ambre, de musc blanc et de santal. Élégant et intemporel.', 'Parfum unisexe nocturne', 3, 'Lumoura', 120.00, 100, 'Eau de Parfum', 'Unisexe', '2026-01-20 14:19:30', 1, 0, 0.00, 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 17, 10),
(12, 'LUM-U-002', 'Zenitude', 'Fragrance apaisante aux notes de thé vert, de bambou et de fleur de lotus. Pour un moment de détente.', 'Parfum zen et relaxant', 3, 'Lumoura', 105.00, 50, 'Eau de Cologne', 'Unisexe', '2026-01-20 14:19:30', 0, 1, 30.00, 'https://images.unsplash.com/photo-1590736969956-6d9c2a8d6972?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 25, 10),
(13, 'LUM-U-003', 'Oasis', 'Fragrance exotique aux notes de figuier, de noix de coco et de fleur d\'ylang-ylang. Évasion garantie.', 'Parfum exotique et fruité', 3, 'Lumoura', 130.00, 100, 'Eau de Parfum', 'Unisexe', '2026-01-20 14:19:30', 0, 1, 15.00, 'https://images.unsplash.com/photo-1600566752355-35792bedcfea?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 40, 10),
(14, 'LUM-U-004', 'Mystic Forest', 'Fragrance forestière aux notes de mousse, de fougère et de cèdre. Une promenade en forêt.', 'Parfum boisé forestier', 3, 'Lumoura', 125.00, 100, 'Eau de Parfum', 'Unisexe', '2026-01-20 14:19:30', 0, 0, 10.00, 'https://images.unsplash.com/photo-1543858741-6c4c5c5c6c6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 0, 10),
(15, 'LUM-U-005', 'Citrus Zen', 'Fragrance énergisante aux notes de pamplemousse, de citron vert et de gingembre. Revitalisant et frais.', 'Parfum d\'agrumes énergisant', 3, 'Lumoura', 98.00, 100, 'Eau Fraiche', 'Unisexe', '2026-01-20 14:19:30', 1, 0, 20.00, 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 83, 10),
(16, 'LUM-F-006', 'Velours Noir', 'Fragrance intense aux notes de rose noire, de patchouli et de bois de oud. Mystérieux et captivant.', 'Parfum intense et mystérieux', 1, 'Lumoura', 175.00, 100, 'Parfum', 'Femme', '2026-01-20 14:19:30', 1, 1, 0.00, 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 20, 10),
(17, 'LUM-H-006', 'Spartiate', 'Fragrance spartiate aux notes de laurier, d\'olivier et de terre humide. Fort et déterminé.', 'Parfum fort et déterminé', 2, 'Lumoura', 142.00, 100, 'Eau de Parfum', 'Homme', '2026-01-20 14:19:30', 1, 1, 25.00, 'https://images.unsplash.com/photo-1590736969956-6d9c2a8d6972?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 40, 10),
(18, 'LUM-U-006', 'Minéral Essence', 'Fragrance minérale aux notes de pierre, d\'eau de source et de cèdre. Pur et naturel.', 'Parfum minéral pur', 3, 'Lumoura', 135.00, 100, 'Eau de Parfum', 'Unisexe', '2026-01-20 14:19:30', 0, 0, 0.00, 'https://images.unsplash.com/photo-1600566752355-35792bedcfea?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 5, 10),
(19, 'LUM-F-007', 'Soie de Soir', 'Fragrance délicate aux notes de magnolia, de pêche et de musc blanc. Douceur et élégance.', 'Parfum doux et élégant', 1, 'Lumoura', 128.00, 100, 'Eau de Parfum', 'Femme', '2026-01-20 14:19:30', 0, 1, 15.00, 'https://images.unsplash.com/photo-1543858741-6c4c5c5c6c6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 56, 10),
(20, 'LUM-H-007', 'Viking Spirit', 'Fragrance nordique aux notes de pin, de baies de genévrier et d\'ambre. Sauvage et libre.', 'Parfum nordique sauvage', 2, 'Lumoura', 148.00, 100, 'Eau de Parfum', 'Homme', '2026-01-20 14:19:30', 1, 0, 10.00, 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 35, 10);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_utilisateur` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `civilite` enum('M','Mme','Mlle') DEFAULT NULL,
  `newsletter` tinyint(1) DEFAULT 0,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `role` enum('client','admin') DEFAULT 'client',
  `adresse_livraison` text DEFAULT NULL,
  `code_postal_livraison` varchar(10) DEFAULT NULL,
  `ville_livraison` varchar(100) DEFAULT NULL,
  `adresse_facturation` text DEFAULT NULL,
  `code_postal_facturation` varchar(10) DEFAULT NULL,
  `ville_facturation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_utilisateur`, `email`, `mot_de_passe`, `nom`, `prenom`, `telephone`, `date_inscription`, `civilite`, `newsletter`, `statut`, `role`, `adresse_livraison`, `code_postal_livraison`, `ville_livraison`, `adresse_facturation`, `code_postal_facturation`, `ville_facturation`) VALUES
(1, 'admin@lumoura.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'System', '0123456789', '2026-01-20 14:19:30', 'M', 1, 'actif', 'admin', '123 Avenue des Champs-Élysées', '75008', 'Paris', NULL, NULL, NULL),
(2, 'marie.durand@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Durand', 'Marie', '0612345678', '2026-01-20 14:19:30', 'Mme', 1, 'actif', 'client', '45 Rue de la République', '69001', 'Lyon', NULL, NULL, NULL),
(3, 'pierre.martin@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Martin', 'Pierre', '0623456789', '2026-01-20 14:19:30', 'M', 0, 'actif', 'client', '12 Boulevard Haussmann', '75009', 'Paris', NULL, NULL, NULL),
(4, 'sophie.leroy@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Leroy', 'Sophie', '0634567890', '2026-01-20 14:19:30', 'Mlle', 1, 'actif', 'client', '8 Place Bellecour', '69002', 'Lyon', NULL, NULL, NULL),
(5, 'test@gmail.com', '$2y$10$bdji5X16B.jJqTrxYEpli.p/eaoPZ3pbddIvT3FotROMnTHw.fu2K', 'test', 'test2', '0769851289', '2026-01-20 14:38:54', 'Mme', 1, 'actif', 'client', NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id_avis`),
  ADD KEY `idx_produit` (`id_produit`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_note` (`note`),
  ADD KEY `idx_avis_produit_utilisateur` (`id_produit`,`id_utilisateur`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_categorie`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_slug` (`slug`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id_commande`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_commande` (`date_commande`),
  ADD KEY `idx_numero_commande` (`numero_commande`),
  ADD KEY `idx_commandes_date` (`date_commande`);

--
-- Index pour la table `details_commande`
--
ALTER TABLE `details_commande`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `idx_commande` (`id_commande`),
  ADD KEY `idx_produit` (`id_produit`),
  ADD KEY `idx_details_commande_produit` (`id_produit`);

--
-- Index pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD PRIMARY KEY (`id_favori`),
  ADD UNIQUE KEY `unique_favori` (`id_utilisateur`,`id_produit`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_produit` (`id_produit`),
  ADD KEY `idx_favoris_utilisateur_produit` (`id_utilisateur`,`id_produit`);

--
-- Index pour la table `newsletters`
--
ALTER TABLE `newsletters`
  ADD PRIMARY KEY (`id_newsletter`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_actif` (`actif`);

--
-- Index pour la table `panier`
--
ALTER TABLE `panier`
  ADD PRIMARY KEY (`id_panier`),
  ADD UNIQUE KEY `unique_panier` (`id_utilisateur`,`id_produit`),
  ADD KEY `id_produit` (`id_produit`),
  ADD KEY `idx_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_panier_utilisateur_produit` (`id_utilisateur`,`id_produit`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id_produit`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_categorie` (`id_categorie`),
  ADD KEY `idx_genre` (`genre`),
  ADD KEY `idx_bestseller` (`bestseller`),
  ADD KEY `idx_nouveaute` (`nouveaute`),
  ADD KEY `idx_promotion` (`promotion_pourcentage`),
  ADD KEY `idx_prix` (`prix`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_marque` (`marque`),
  ADD KEY `idx_concentration` (`concentration`),
  ADD KEY `idx_date_ajout` (`date_ajout`),
  ADD KEY `idx_produits_categorie` (`id_categorie`),
  ADD KEY `idx_produits_date_ajout` (`date_ajout`),
  ADD KEY `idx_produits_prix` (`prix`),
  ADD KEY `idx_produits_stock` (`stock`);
ALTER TABLE `produits` ADD FULLTEXT KEY `idx_recherche_produits` (`nom`,`description`,`description_courte`,`marque`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `avis`
--
ALTER TABLE `avis`
  MODIFY `id_avis` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `details_commande`
--
ALTER TABLE `details_commande`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `favoris`
--
ALTER TABLE `favoris`
  MODIFY `id_favori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `newsletters`
--
ALTER TABLE `newsletters`
  MODIFY `id_newsletter` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `panier`
--
ALTER TABLE `panier`
  MODIFY `id_panier` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`),
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id_categorie`);

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `details_commande`
--
ALTER TABLE `details_commande`
  ADD CONSTRAINT `details_commande_ibfk_1` FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`) ON DELETE CASCADE,
  ADD CONSTRAINT `details_commande_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

--
-- Contraintes pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE;

--
-- Contraintes pour la table `panier`
--
ALTER TABLE `panier`
  ADD CONSTRAINT `panier_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `panier_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE;

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`id_categorie`) REFERENCES `categories` (`id_categorie`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
