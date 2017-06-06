SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `uuid` varchar(36) NOT NULL,
  `user` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sync_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `client` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `fcm_id` text NOT NULL,
  `last_sync` timestamp(3) NOT NULL DEFAULT '1970-01-01 08:00:00.000',
  `lock_expire` timestamp(3) NULL DEFAULT NULL,
  `changes_pending` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `deleted` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `type` enum('cat','entry') NOT NULL,
  `cat` int(11) DEFAULT NULL,
  `uuid` varchar(36) NOT NULL,
  `sync_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `client` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `entries` (
  `id` int(11) NOT NULL,
  `uuid` varchar(36) NOT NULL,
  `user` int(11) NOT NULL,
  `cat` int(11) NOT NULL,
  `title` text NOT NULL,
  `maker` text,
  `origin` text,
  `price` text,
  `location` text,
  `date` bigint(20) NOT NULL DEFAULT '0',
  `rating` decimal(5,1) NOT NULL DEFAULT '0.0',
  `notes` text,
  `sync_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `client` int(11) NOT NULL DEFAULT '0',
  `publish_time` timestamp(3) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `entries_extras` (
  `entry` int(11) NOT NULL,
  `extra` int(11) NOT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `entries_flavors` (
  `entry` int(11) NOT NULL,
  `flavor` varchar(255) NOT NULL,
  `value` int(11) NOT NULL DEFAULT '0',
  `pos` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `extras` (
  `id` int(11) NOT NULL,
  `uuid` varchar(36) NOT NULL,
  `cat` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pos` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `flavors` (
  `id` int(11) NOT NULL,
  `cat` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pos` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `photos` (
  `id` int(11) NOT NULL,
  `entry` int(11) NOT NULL,
  `hash` varchar(32) NOT NULL,
  `drive_id` text,
  `pos` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `uid` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`,`user`),
  ADD KEY `user` (`user`);

ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`);

ALTER TABLE `deleted`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`),
  ADD KEY `cat` (`cat`);

ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`,`user`),
  ADD KEY `user` (`user`),
  ADD KEY `cat` (`cat`);

ALTER TABLE `entries_extras`
  ADD UNIQUE KEY `entry_2` (`entry`,`extra`),
  ADD KEY `entry` (`entry`),
  ADD KEY `extra` (`extra`);

ALTER TABLE `entries_flavors`
  ADD KEY `entry` (`entry`);

ALTER TABLE `extras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`,`cat`),
  ADD KEY `cat` (`cat`);

ALTER TABLE `flavors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cat` (`cat`);

ALTER TABLE `photos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entry_2` (`entry`,`hash`),
  ADD KEY `entry` (`entry`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`);


ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;
ALTER TABLE `deleted`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;
ALTER TABLE `extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;
ALTER TABLE `flavors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=391;
ALTER TABLE `photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `deleted`
  ADD CONSTRAINT `deleted_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `deleted_ibfk_2` FOREIGN KEY (`cat`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `entries`
  ADD CONSTRAINT `entries_ibfk_2` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `entries_ibfk_3` FOREIGN KEY (`cat`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `entries_extras`
  ADD CONSTRAINT `entries_extras_ibfk_1` FOREIGN KEY (`entry`) REFERENCES `entries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `entries_extras_ibfk_2` FOREIGN KEY (`extra`) REFERENCES `extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `entries_flavors`
  ADD CONSTRAINT `entries_flavors_ibfk_1` FOREIGN KEY (`entry`) REFERENCES `entries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `extras`
  ADD CONSTRAINT `extras_ibfk_1` FOREIGN KEY (`cat`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `flavors`
  ADD CONSTRAINT `flavors_ibfk_1` FOREIGN KEY (`cat`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `photos`
  ADD CONSTRAINT `photos_ibfk_1` FOREIGN KEY (`entry`) REFERENCES `entries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
