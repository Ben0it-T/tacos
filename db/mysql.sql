
--
-- Structure for table `tacos_activities`
--

CREATE TABLE `tacos_activities` (
  `id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `visible` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_activities_teams`
--

CREATE TABLE `tacos_activities_teams` (
  `activity_id` int NOT NULL,
  `team_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_customers`
--

CREATE TABLE `tacos_customers` (
  `id` int NOT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `visible` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_customers_teams`
--

CREATE TABLE `tacos_customers_teams` (
  `customer_id` int NOT NULL,
  `team_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_projects`
--

CREATE TABLE `tacos_projects` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `start` date DEFAULT NULL,
  `end` date DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `global_activities` tinyint(1) NOT NULL DEFAULT '1',
  `visible` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_projects_activities`
--

CREATE TABLE `tacos_projects_activities` (
  `project_id` int NOT NULL,
  `activity_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_projects_teams`
--

CREATE TABLE `tacos_projects_teams` (
  `project_id` int NOT NULL,
  `team_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_roles`
--

CREATE TABLE `tacos_roles` (
  `id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tacos_roles` (`id`, `name`) VALUES
(3, 'ROLE_ADMIN'),
(2, 'ROLE_TEAMLEAD'),
(1, 'ROLE_USER');

-- --------------------------------------------------------

--
-- Structure for table `tacos_sessions`
--

CREATE TABLE `tacos_sessions` (
  `id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `time` int UNSIGNED DEFAULT NULL,
  `data` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_tags`
--

CREATE TABLE `tacos_tags` (
  `id` int NOT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visible` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_teams`
--

CREATE TABLE `tacos_teams` (
  `id` int NOT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_timesheet`
--

CREATE TABLE `tacos_timesheet` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `activity_id` int NOT NULL,
  `project_id` int NOT NULL,
  `start` datetime NOT NULL,
  `end` datetime DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_timesheet_tags`
--

CREATE TABLE `tacos_timesheet_tags` (
  `timesheet_id` int NOT NULL,
  `tag_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_users`
--

CREATE TABLE `tacos_users` (
  `id` int NOT NULL,
  `username` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `registration_date` datetime DEFAULT NULL,
  `role_id` int NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `password_request_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_request_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for table `tacos_users_teams`
--

CREATE TABLE `tacos_users_teams` (
  `user_id` int NOT NULL,
  `team_id` int NOT NULL,
  `teamlead` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



--
-- Index for table `tacos_activities`
--
ALTER TABLE `tacos_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx-project_id` (`project_id`),
  ADD KEY `idx-visible` (`visible`);

--
-- Index for table `tacos_activities_teams`
--
ALTER TABLE `tacos_activities_teams`
  ADD PRIMARY KEY (`activity_id`,`team_id`),
  ADD KEY `idx-activity_id` (`activity_id`),
  ADD KEY `idx-team_id` (`team_id`);

--
-- Index for table `tacos_customers`
--
ALTER TABLE `tacos_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx-visible` (`visible`) USING BTREE;

--
-- Index for table `tacos_customers_teams`
--
ALTER TABLE `tacos_customers_teams`
  ADD PRIMARY KEY (`customer_id`,`team_id`),
  ADD KEY `idx-customers_id` (`customer_id`) USING BTREE,
  ADD KEY `idx-team_id` (`team_id`) USING BTREE;

--
-- Index for table `tacos_projects`
--
ALTER TABLE `tacos_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx-customer_id` (`customer_id`);

--
-- Index for table `tacos_projects_activities`
--
ALTER TABLE `tacos_projects_activities`
  ADD PRIMARY KEY (`project_id`,`activity_id`),
  ADD KEY `idx-project_id` (`project_id`),
  ADD KEY `idx-activity_id` (`activity_id`);

--
-- Index for table `tacos_projects_teams`
--
ALTER TABLE `tacos_projects_teams`
  ADD PRIMARY KEY (`project_id`,`team_id`),
  ADD KEY `idx-project_id` (`project_id`),
  ADD KEY `idx-team_id` (`team_id`);

--
-- Index for table `tacos_roles`
--
ALTER TABLE `tacos_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx-name` (`name`) USING BTREE;

--
-- Index for table `tacos_sessions`
--
ALTER TABLE `tacos_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Index for table `tacos_tags`
--
ALTER TABLE `tacos_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx-name` (`name`),
  ADD KEY `idx-visible` (`visible`);

--
-- Index for table `tacos_teams`
--
ALTER TABLE `tacos_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx-name` (`name`) USING BTREE;

--
-- Index for table `tacos_timesheet`
--
ALTER TABLE `tacos_timesheet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx-user_id` (`user_id`),
  ADD KEY `idx-activity_id` (`activity_id`),
  ADD KEY `idx-project_id` (`project_id`),
  ADD KEY `idx-start` (`start`),
  ADD KEY `idx-end` (`end`);

--
-- Index for table `tacos_timesheet_tags`
--
ALTER TABLE `tacos_timesheet_tags`
  ADD PRIMARY KEY (`timesheet_id`,`tag_id`),
  ADD KEY `idx-timesheet_id` (`timesheet_id`),
  ADD KEY `idx-tag_id` (`tag_id`);

--
-- Index for table `tacos_users`
--
ALTER TABLE `tacos_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx-username` (`username`) USING BTREE,
  ADD UNIQUE KEY `idx-email` (`email`) USING BTREE,
  ADD UNIQUE KEY `idx-password_request_token` (`password_request_token`) USING BTREE,
  ADD KEY `idx-role_id` (`role_id`),
  ADD KEY `idx-password_request_date` (`password_request_date`);

--
-- Index for table `tacos_users_teams`
--
ALTER TABLE `tacos_users_teams`
  ADD PRIMARY KEY (`user_id`,`team_id`),
  ADD KEY `idx-user_id` (`user_id`) USING BTREE,
  ADD KEY `idx-team_id` (`team_id`) USING BTREE;



--
-- AUTO_INCREMENT for table `tacos_activities`
--
ALTER TABLE `tacos_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_customers`
--
ALTER TABLE `tacos_customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_projects`
--
ALTER TABLE `tacos_projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_roles`
--
ALTER TABLE `tacos_roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tacos_tags`
--
ALTER TABLE `tacos_tags`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_teams`
--
ALTER TABLE `tacos_teams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_timesheet`
--
ALTER TABLE `tacos_timesheet`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tacos_users`
--
ALTER TABLE `tacos_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;



--
-- Constraints for table `tacos_activities`
--
ALTER TABLE `tacos_activities`
  ADD CONSTRAINT `fk-tacos_activities-project_id` FOREIGN KEY (`project_id`) REFERENCES `tacos_projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_activities_teams`
--
ALTER TABLE `tacos_activities_teams`
  ADD CONSTRAINT `fk-tacos_activities_teams-activity_id` FOREIGN KEY (`activity_id`) REFERENCES `tacos_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk-tacos_activities_teams-team_id` FOREIGN KEY (`team_id`) REFERENCES `tacos_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_customers_teams`
--
ALTER TABLE `tacos_customers_teams`
  ADD CONSTRAINT `fk-tacos_customers_teams-customer_id` FOREIGN KEY (`customer_id`) REFERENCES `tacos_customers` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk-tacos_customers_teams-team_id` FOREIGN KEY (`team_id`) REFERENCES `tacos_teams` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Constraints for table `tacos_projects`
--
ALTER TABLE `tacos_projects`
  ADD CONSTRAINT `fk-tacos_projects-customer_id` FOREIGN KEY (`customer_id`) REFERENCES `tacos_customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_projects_activities`
--
ALTER TABLE `tacos_projects_activities`
  ADD CONSTRAINT `fk-tacos_projects_activities-activity_id` FOREIGN KEY (`activity_id`) REFERENCES `tacos_activities` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk-tacos_projects_activities-project_id` FOREIGN KEY (`project_id`) REFERENCES `tacos_projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_projects_teams`
--
ALTER TABLE `tacos_projects_teams`
  ADD CONSTRAINT `fk-tacos_projects_teams-project_id` FOREIGN KEY (`project_id`) REFERENCES `tacos_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk-tacos_projects_teams-team_id` FOREIGN KEY (`team_id`) REFERENCES `tacos_teams` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Constraints for table `tacos_timesheet`
--
ALTER TABLE `tacos_timesheet`
  ADD CONSTRAINT `fk-tacos_timesheet-activity_id` FOREIGN KEY (`activity_id`) REFERENCES `tacos_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk-tacos_timesheet-project_id` FOREIGN KEY (`project_id`) REFERENCES `tacos_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk-tacos_timesheet-user_id` FOREIGN KEY (`user_id`) REFERENCES `tacos_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_timesheet_tags`
--
ALTER TABLE `tacos_timesheet_tags`
  ADD CONSTRAINT `fk-tacos_timesheet_tags-tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tacos_tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk-tacos_timesheet_tags-timesheet_id` FOREIGN KEY (`timesheet_id`) REFERENCES `tacos_timesheet` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tacos_users`
--
ALTER TABLE `tacos_users`
  ADD CONSTRAINT `fk-tacos_users-role_id` FOREIGN KEY (`role_id`) REFERENCES `tacos_roles` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `tacos_users_teams`
--
ALTER TABLE `tacos_users_teams`
  ADD CONSTRAINT `fk-tacos_users_teams-team_id` FOREIGN KEY (`team_id`) REFERENCES `tacos_teams` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk-tacos_users_teams-user_id` FOREIGN KEY (`user_id`) REFERENCES `tacos_users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

