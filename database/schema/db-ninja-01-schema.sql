/*!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan` enum('pro','enterprise','white_label') DEFAULT NULL,
  `plan_term` enum('month','year') DEFAULT NULL,
  `plan_started` date DEFAULT NULL,
  `plan_paid` date DEFAULT NULL,
  `plan_expires` date DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `key` varchar(191) DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `default_company_id` int(10) unsigned NOT NULL,
  `trial_started` date DEFAULT NULL,
  `trial_plan` enum('pro','enterprise') DEFAULT NULL,
  `plan_price` decimal(7,2) DEFAULT NULL,
  `num_users` smallint(6) NOT NULL DEFAULT 1,
  `utm_source` varchar(191) DEFAULT NULL,
  `utm_medium` varchar(191) DEFAULT NULL,
  `utm_campaign` varchar(191) DEFAULT NULL,
  `utm_term` varchar(191) DEFAULT NULL,
  `utm_content` varchar(191) DEFAULT NULL,
  `latest_version` varchar(191) NOT NULL DEFAULT '0.0.0',
  `report_errors` tinyint(1) NOT NULL DEFAULT 0,
  `referral_code` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `is_scheduler_running` tinyint(1) NOT NULL DEFAULT 0,
  `trial_duration` int(10) unsigned DEFAULT NULL,
  `is_onboarding` tinyint(1) NOT NULL DEFAULT 0,
  `onboarding` mediumtext DEFAULT NULL,
  `is_migrated` tinyint(1) NOT NULL DEFAULT 0,
  `platform` varchar(128) DEFAULT NULL,
  `hosted_client_count` int(10) unsigned DEFAULT NULL,
  `hosted_company_count` int(10) unsigned DEFAULT NULL,
  `inapp_transaction_id` varchar(100) DEFAULT NULL,
  `set_react_as_default_ap` tinyint(1) NOT NULL DEFAULT 1,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `is_verified_account` tinyint(1) NOT NULL DEFAULT 0,
  `account_sms_verification_code` text DEFAULT NULL,
  `account_sms_verification_number` text DEFAULT NULL,
  `account_sms_verified` tinyint(1) NOT NULL DEFAULT 0,
  `bank_integration_account_id` text DEFAULT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT 0,
  `email_quota` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_payment_id_index` (`payment_id`),
  KEY `accounts_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `client_contact_id` int(10) unsigned DEFAULT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `credit_id` int(10) unsigned DEFAULT NULL,
  `invitation_id` int(10) unsigned DEFAULT NULL,
  `task_id` int(10) unsigned DEFAULT NULL,
  `expense_id` int(10) unsigned DEFAULT NULL,
  `activity_type_id` int(10) unsigned DEFAULT NULL,
  `ip` varchar(191) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `token_id` int(10) unsigned DEFAULT NULL,
  `quote_id` int(10) unsigned DEFAULT NULL,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `recurring_invoice_id` int(10) unsigned DEFAULT NULL,
  `recurring_expense_id` int(10) unsigned DEFAULT NULL,
  `recurring_quote_id` int(10) unsigned DEFAULT NULL,
  `purchase_order_id` int(10) unsigned DEFAULT NULL,
  `vendor_contact_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_vendor_id_company_id_index` (`vendor_id`,`company_id`),
  KEY `activities_project_id_company_id_index` (`project_id`,`company_id`),
  KEY `activities_user_id_company_id_index` (`user_id`,`company_id`),
  KEY `activities_client_id_company_id_index` (`client_id`,`company_id`),
  KEY `activities_payment_id_company_id_index` (`payment_id`,`company_id`),
  KEY `activities_invoice_id_company_id_index` (`invoice_id`,`company_id`),
  KEY `activities_credit_id_company_id_index` (`credit_id`,`company_id`),
  KEY `activities_invitation_id_company_id_index` (`invitation_id`,`company_id`),
  KEY `activities_task_id_company_id_index` (`task_id`,`company_id`),
  KEY `activities_expense_id_company_id_index` (`expense_id`,`company_id`),
  KEY `activities_client_contact_id_company_id_index` (`client_contact_id`,`company_id`),
  KEY `activities_company_id_foreign` (`company_id`),
  KEY `activities_quote_id_company_id_index` (`quote_id`,`company_id`),
  KEY `activities_recurring_invoice_id_company_id_index` (`recurring_invoice_id`,`company_id`),
  KEY `activities_purchase_order_id_company_id_index` (`purchase_order_id`,`company_id`),
  KEY `activities_vendor_contact_id_company_id_index` (`vendor_contact_id`,`company_id`),
  CONSTRAINT `activities_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `json_backup` mediumtext DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `amount` decimal(16,4) NOT NULL,
  `filename` text DEFAULT NULL,
  `disk` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backups_activity_id_foreign` (`activity_id`),
  CONSTRAINT `backups_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `bank_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_companies_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `bank_companies_user_id_foreign` (`user_id`),
  KEY `bank_companies_bank_id_foreign` (`bank_id`),
  CONSTRAINT `bank_companies_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  CONSTRAINT `bank_companies_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_companies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_integrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `provider_name` text NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `bank_account_id` bigint(20) NOT NULL,
  `bank_account_name` text DEFAULT NULL,
  `bank_account_number` text DEFAULT NULL,
  `bank_account_status` text DEFAULT NULL,
  `bank_account_type` text DEFAULT NULL,
  `balance` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `currency` text DEFAULT NULL,
  `nickname` text NOT NULL DEFAULT '',
  `from_date` date DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `disabled_upstream` tinyint(1) NOT NULL DEFAULT 0,
  `auto_sync` tinyint(1) NOT NULL DEFAULT 0,
  `integration_type` varchar(191) DEFAULT NULL,
  `nordigen_account_id` varchar(191) DEFAULT NULL,
  `nordigen_institution_id` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_integrations_user_id_foreign` (`user_id`),
  KEY `bank_integrations_account_id_foreign` (`account_id`),
  KEY `bank_integrations_company_id_foreign` (`company_id`),
  CONSTRAINT `bank_integrations_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_integrations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_integrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_subcompanies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_subcompanies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `bank_company_id` int(10) unsigned NOT NULL,
  `account_name` varchar(191) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_subcompanies_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `bank_subcompanies_user_id_foreign` (`user_id`),
  KEY `bank_subcompanies_bank_company_id_foreign` (`bank_company_id`),
  CONSTRAINT `bank_subcompanies_bank_company_id_foreign` FOREIGN KEY (`bank_company_id`) REFERENCES `bank_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_subcompanies_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_subcompanies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transaction_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transaction_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `rules` mediumtext DEFAULT NULL,
  `auto_convert` tinyint(1) NOT NULL DEFAULT 0,
  `matches_on_all` tinyint(1) NOT NULL DEFAULT 0,
  `applies_to` varchar(191) NOT NULL DEFAULT 'CREDIT',
  `client_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `on_credit_match` enum('create_payment','link_payment') NOT NULL DEFAULT 'create_payment',
  PRIMARY KEY (`id`),
  KEY `bank_transaction_rules_user_id_foreign` (`user_id`),
  KEY `bank_transaction_rules_company_id_foreign` (`company_id`),
  CONSTRAINT `bank_transaction_rules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_transaction_rules_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `bank_integration_id` bigint(20) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `currency_code` varchar(191) DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `account_type` varchar(191) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `ninja_category_id` int(10) unsigned DEFAULT NULL,
  `category_type` varchar(191) NOT NULL,
  `base_type` varchar(191) NOT NULL,
  `date` date DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `invoice_ids` text NOT NULL DEFAULT '',
  `expense_id` text DEFAULT '',
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `bank_transaction_rule_id` bigint(20) DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `participant` varchar(191) DEFAULT NULL,
  `participant_name` varchar(191) DEFAULT NULL,
  `nordigen_transaction_id` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_transactions_bank_integration_id_foreign` (`bank_integration_id`),
  KEY `bank_transactions_user_id_foreign` (`user_id`),
  KEY `bank_transactions_company_id_foreign` (`company_id`),
  KEY `bank_transactions_transaction_id_index` (`transaction_id`),
  KEY `bank_transactions_category_type_index` (`category_type`),
  KEY `bank_transactions_base_type_index` (`base_type`),
  CONSTRAINT `bank_transactions_bank_integration_id_foreign` FOREIGN KEY (`bank_integration_id`) REFERENCES `bank_integrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_transactions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bank_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) DEFAULT NULL,
  `remote_id` varchar(191) DEFAULT NULL,
  `bank_library_id` int(11) NOT NULL DEFAULT 1,
  `config` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `first_name` varchar(191) DEFAULT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `confirmation_code` varchar(191) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_logins` smallint(6) DEFAULT NULL,
  `oauth_user_id` varchar(100) DEFAULT NULL,
  `oauth_provider_id` int(10) unsigned DEFAULT NULL,
  `google_2fa_secret` varchar(191) DEFAULT NULL,
  `accepted_terms_version` varchar(191) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_type` varchar(255) DEFAULT NULL,
  `avatar_size` varchar(255) DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `token` varchar(191) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `send_email` tinyint(1) NOT NULL DEFAULT 1,
  `contact_key` varchar(191) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_contacts_oauth_user_id_unique` (`oauth_user_id`),
  UNIQUE KEY `client_contacts_oauth_provider_id_unique` (`oauth_provider_id`),
  KEY `client_contacts_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `client_contacts_company_id_email_deleted_at_index` (`company_id`,`email`,`deleted_at`),
  KEY `client_contacts_company_id_index` (`company_id`),
  KEY `client_contacts_client_id_index` (`client_id`),
  KEY `client_contacts_user_id_index` (`user_id`),
  KEY `client_contacts_contact_key(20)_index` (`contact_key`(20)),
  KEY `client_contacts_email_index` (`email`),
  CONSTRAINT `client_contacts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_gateway_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_gateway_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `token` text DEFAULT NULL,
  `routing_number` text DEFAULT NULL,
  `company_gateway_id` int(10) unsigned NOT NULL,
  `gateway_customer_reference` varchar(191) DEFAULT NULL,
  `gateway_type_id` int(10) unsigned NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `meta` text DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_gateway_tokens_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `client_gateway_tokens_client_id_foreign` (`client_id`),
  CONSTRAINT `client_gateway_tokens_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `client_gateway_tokens_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `recurring_invoice_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `trial_started` int(10) unsigned DEFAULT NULL,
  `trial_ends` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `client_subscriptions_subscription_id_foreign` (`subscription_id`),
  KEY `client_subscriptions_recurring_invoice_id_foreign` (`recurring_invoice_id`),
  KEY `client_subscriptions_client_id_foreign` (`client_id`),
  KEY `client_subscriptions_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `client_subscriptions_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `client_subscriptions_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `client_subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_subscriptions_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `client_subscriptions_recurring_invoice_id_foreign` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices` (`id`),
  CONSTRAINT `client_subscriptions_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `client_hash` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `balance` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `credit_balance` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `last_login` timestamp NULL DEFAULT NULL,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `address1` varchar(191) DEFAULT NULL,
  `address2` varchar(191) DEFAULT NULL,
  `city` varchar(191) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `postal_code` varchar(191) DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `shipping_address1` varchar(191) DEFAULT NULL,
  `shipping_address2` varchar(191) DEFAULT NULL,
  `shipping_city` varchar(191) DEFAULT NULL,
  `shipping_state` varchar(191) DEFAULT NULL,
  `shipping_postal_code` varchar(191) DEFAULT NULL,
  `shipping_country_id` int(10) unsigned DEFAULT NULL,
  `settings` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `group_settings_id` int(10) unsigned DEFAULT NULL,
  `vat_number` varchar(191) DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `id_number` varchar(191) DEFAULT NULL,
  `payment_balance` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `routing_id` varchar(191) DEFAULT NULL,
  `tax_data` mediumtext DEFAULT NULL,
  `is_tax_exempt` tinyint(1) NOT NULL DEFAULT 0,
  `has_valid_vat_number` tinyint(1) NOT NULL DEFAULT 0,
  `classification` varchar(191) DEFAULT NULL,
  `e_invoice` mediumtext DEFAULT NULL,
  `sync` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_company_id_number_unique` (`company_id`,`number`),
  KEY `clients_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `clients_industry_id_foreign` (`industry_id`),
  KEY `clients_size_id_foreign` (`size_id`),
  KEY `clients_company_id_index` (`company_id`),
  KEY `clients_user_id_index` (`user_id`),
  KEY `clients_client_hash(20)_index` (`client_hash`(20)),
  CONSTRAINT `clients_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `clients_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `clients_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `ip` varchar(191) DEFAULT NULL,
  `company_key` varchar(100) NOT NULL,
  `convert_products` tinyint(1) NOT NULL DEFAULT 0,
  `fill_products` tinyint(1) NOT NULL DEFAULT 1,
  `update_products` tinyint(1) NOT NULL DEFAULT 1,
  `show_product_details` tinyint(1) NOT NULL DEFAULT 1,
  `client_can_register` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_taxes1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_taxes2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_taxes3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_taxes4` tinyint(1) NOT NULL DEFAULT 0,
  `show_product_cost` tinyint(1) NOT NULL DEFAULT 0,
  `enabled_tax_rates` int(10) unsigned NOT NULL DEFAULT 0,
  `enabled_modules` int(10) unsigned NOT NULL DEFAULT 0,
  `enable_product_cost` tinyint(1) NOT NULL DEFAULT 0,
  `enable_product_quantity` tinyint(1) NOT NULL DEFAULT 1,
  `default_quantity` tinyint(1) NOT NULL DEFAULT 1,
  `subdomain` varchar(191) DEFAULT NULL,
  `db` varchar(191) DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `first_day_of_week` varchar(191) DEFAULT NULL,
  `first_month_of_year` varchar(191) DEFAULT NULL,
  `portal_mode` varchar(191) NOT NULL DEFAULT 'subdomain',
  `portal_domain` varchar(191) DEFAULT NULL,
  `enable_modules` smallint(6) NOT NULL DEFAULT 0,
  `custom_fields` mediumtext NOT NULL,
  `settings` mediumtext NOT NULL,
  `slack_webhook_url` varchar(191) NOT NULL,
  `google_analytics_key` varchar(191) NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `enabled_item_tax_rates` int(11) NOT NULL DEFAULT 0,
  `is_large` tinyint(1) NOT NULL DEFAULT 0,
  `enable_shop_api` tinyint(1) NOT NULL DEFAULT 0,
  `default_auto_bill` enum('off','always','optin','optout') NOT NULL DEFAULT 'off',
  `mark_expenses_invoiceable` tinyint(1) NOT NULL DEFAULT 0,
  `mark_expenses_paid` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_expense_documents` tinyint(1) NOT NULL DEFAULT 0,
  `auto_start_tasks` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_task_timelog` tinyint(1) NOT NULL DEFAULT 1,
  `invoice_task_documents` tinyint(1) NOT NULL DEFAULT 0,
  `show_tasks_table` tinyint(1) NOT NULL DEFAULT 0,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0,
  `default_task_is_date_based` tinyint(1) NOT NULL DEFAULT 0,
  `enable_product_discount` tinyint(1) NOT NULL DEFAULT 0,
  `calculate_expense_tax_by_amount` tinyint(1) NOT NULL,
  `expense_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `session_timeout` int(11) NOT NULL DEFAULT 0,
  `oauth_password_required` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_task_datelog` tinyint(1) NOT NULL DEFAULT 1,
  `default_password_timeout` int(11) NOT NULL DEFAULT 30,
  `show_task_end_date` tinyint(1) NOT NULL DEFAULT 0,
  `markdown_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `use_comma_as_decimal_place` tinyint(1) NOT NULL DEFAULT 0,
  `report_include_drafts` tinyint(1) NOT NULL DEFAULT 0,
  `client_registration_fields` mediumtext DEFAULT NULL,
  `convert_rate_to_client` tinyint(1) NOT NULL DEFAULT 1,
  `markdown_email_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `stop_on_unpaid_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `use_quote_terms_on_conversion` tinyint(1) NOT NULL DEFAULT 0,
  `enable_applying_payments` tinyint(1) NOT NULL DEFAULT 0,
  `track_inventory` tinyint(1) NOT NULL DEFAULT 0,
  `inventory_notification_threshold` int(11) NOT NULL DEFAULT 0,
  `stock_notification` tinyint(1) NOT NULL DEFAULT 1,
  `matomo_url` varchar(191) DEFAULT NULL,
  `matomo_id` bigint(20) DEFAULT NULL,
  `enabled_expense_tax_rates` int(10) unsigned NOT NULL DEFAULT 0,
  `invoice_task_project` tinyint(1) NOT NULL DEFAULT 0,
  `report_include_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_task_lock` tinyint(1) NOT NULL DEFAULT 0,
  `convert_payment_currency` tinyint(1) NOT NULL DEFAULT 0,
  `convert_expense_currency` tinyint(1) NOT NULL DEFAULT 0,
  `notify_vendor_when_paid` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_task_hours` tinyint(1) NOT NULL DEFAULT 0,
  `calculate_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `tax_data` mediumtext DEFAULT NULL,
  `shopify_name` varchar(191) DEFAULT NULL,
  `shopify_access_token` varchar(191) DEFAULT NULL,
  `e_invoice_certificate` text DEFAULT NULL,
  `e_invoice_certificate_passphrase` text DEFAULT NULL,
  `origin_tax_data` text DEFAULT NULL,
  `invoice_task_project_header` tinyint(1) NOT NULL DEFAULT 1,
  `invoice_task_item_description` tinyint(1) NOT NULL DEFAULT 1,
  `smtp_host` varchar(191) DEFAULT NULL,
  `smtp_port` int(10) unsigned DEFAULT NULL,
  `smtp_encryption` varchar(191) DEFAULT NULL,
  `smtp_username` text DEFAULT NULL,
  `smtp_password` text DEFAULT NULL,
  `smtp_local_domain` varchar(191) DEFAULT NULL,
  `smtp_verify_peer` tinyint(1) NOT NULL DEFAULT 1,
  `e_invoice` mediumtext DEFAULT NULL,
  `expense_mailbox_active` tinyint(1) NOT NULL DEFAULT 0,
  `expense_mailbox` varchar(191) DEFAULT NULL,
  `inbound_mailbox_allow_company_users` tinyint(1) NOT NULL DEFAULT 0,
  `inbound_mailbox_allow_vendors` tinyint(1) NOT NULL DEFAULT 0,
  `inbound_mailbox_allow_clients` tinyint(1) NOT NULL DEFAULT 0,
  `inbound_mailbox_allow_unknown` tinyint(1) NOT NULL DEFAULT 0,
  `inbound_mailbox_whitelist` text DEFAULT NULL,
  `inbound_mailbox_blacklist` text DEFAULT NULL,
  `quickbooks` text DEFAULT NULL,
  `legal_entity_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_company_key_unique` (`company_key`),
  KEY `companies_industry_id_foreign` (`industry_id`),
  KEY `companies_size_id_foreign` (`size_id`),
  KEY `companies_account_id_index` (`account_id`),
  KEY `companies_subdomain_portal_mode_index` (`subdomain`,`portal_mode`),
  KEY `companies_portal_domain_portal_mode_index` (`portal_domain`,`portal_mode`),
  KEY `companies_company_key_index` (`company_key`),
  KEY `companies_shopify_name_index` (`shopify_name`),
  KEY `companies_shopify_access_token_index` (`shopify_access_token`),
  CONSTRAINT `companies_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `companies_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `companies_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `gateway_key` varchar(191) NOT NULL,
  `accepted_credit_cards` int(10) unsigned NOT NULL,
  `require_cvv` tinyint(1) NOT NULL DEFAULT 1,
  `require_billing_address` tinyint(1) DEFAULT 1,
  `require_shipping_address` tinyint(1) DEFAULT 1,
  `update_details` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `config` mediumtext NOT NULL,
  `fees_and_limits` text NOT NULL,
  `custom_value1` varchar(191) DEFAULT NULL,
  `custom_value2` varchar(191) DEFAULT NULL,
  `custom_value3` varchar(191) DEFAULT NULL,
  `custom_value4` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `token_billing` enum('off','always','optin','optout') NOT NULL DEFAULT 'off',
  `label` varchar(255) DEFAULT NULL,
  `require_client_name` tinyint(1) NOT NULL DEFAULT 0,
  `require_postal_code` tinyint(1) NOT NULL DEFAULT 0,
  `require_client_phone` tinyint(1) NOT NULL DEFAULT 0,
  `require_contact_name` tinyint(1) NOT NULL DEFAULT 0,
  `require_contact_email` tinyint(1) NOT NULL DEFAULT 0,
  `require_custom_value1` tinyint(1) NOT NULL DEFAULT 0,
  `require_custom_value2` tinyint(1) NOT NULL DEFAULT 0,
  `require_custom_value3` tinyint(1) NOT NULL DEFAULT 0,
  `require_custom_value4` tinyint(1) NOT NULL DEFAULT 0,
  `always_show_required_fields` tinyint(1) NOT NULL DEFAULT 1,
  `settings` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_gateways_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `company_gateways_gateway_key_foreign` (`gateway_key`),
  KEY `company_gateways_user_id_foreign` (`user_id`),
  CONSTRAINT `company_gateways_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `company_gateways_gateway_key_foreign` FOREIGN KEY (`gateway_key`) REFERENCES `gateways` (`key`),
  CONSTRAINT `company_gateways_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_ledgers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `activity_id` int(10) unsigned DEFAULT NULL,
  `adjustment` decimal(20,6) DEFAULT NULL,
  `balance` decimal(20,6) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `hash` text DEFAULT NULL,
  `company_ledgerable_id` int(10) unsigned NOT NULL,
  `company_ledgerable_type` varchar(191) NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_ledgers_company_id_foreign` (`company_id`),
  KEY `company_ledgers_client_id_foreign` (`client_id`),
  CONSTRAINT `company_ledgers_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `company_ledgers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `company_tokens_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `company_tokens_account_id_foreign` (`account_id`),
  KEY `company_tokens_user_id_foreign` (`user_id`),
  KEY `company_tokens_company_id_index` (`company_id`),
  KEY `company_tokens_token_deleted_at_index` (`token`,`deleted_at`),
  CONSTRAINT `company_tokens_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `company_tokens_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `company_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `permissions` mediumtext DEFAULT NULL,
  `notifications` mediumtext DEFAULT NULL,
  `settings` mediumtext DEFAULT NULL,
  `slack_webhook_url` varchar(191) NOT NULL,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `permissions_updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ninja_portal_url` text NOT NULL DEFAULT '',
  `react_settings` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_user_company_id_user_id_unique` (`company_id`,`user_id`),
  KEY `company_user_account_id_company_id_deleted_at_index` (`account_id`,`company_id`,`deleted_at`),
  KEY `company_user_user_id_index` (`user_id`),
  CONSTRAINT `company_user_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_user_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `capital` varchar(255) DEFAULT NULL,
  `citizenship` varchar(255) DEFAULT NULL,
  `country_code` varchar(4) NOT NULL,
  `currency` varchar(255) DEFAULT NULL,
  `currency_code` varchar(255) DEFAULT NULL,
  `currency_sub_unit` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `iso_3166_2` varchar(5) NOT NULL,
  `iso_3166_3` varchar(3) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `region_code` varchar(3) DEFAULT NULL,
  `sub_region_code` varchar(3) DEFAULT NULL,
  `eea` tinyint(1) NOT NULL DEFAULT 0,
  `swap_postal_code` tinyint(1) NOT NULL DEFAULT 0,
  `swap_currency_symbol` tinyint(1) NOT NULL DEFAULT 0,
  `thousand_separator` varchar(191) DEFAULT '',
  `decimal_separator` varchar(191) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credit_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_contact_id` int(10) unsigned NOT NULL,
  `credit_id` int(10) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `signature_ip` text DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credit_invitations_client_contact_id_credit_id_unique` (`client_contact_id`,`credit_id`),
  KEY `credit_invitations_user_id_foreign` (`user_id`),
  KEY `credit_invitations_company_id_foreign` (`company_id`),
  KEY `credit_invitations_deleted_at_credit_id_company_id_index` (`deleted_at`,`credit_id`,`company_id`),
  KEY `credit_invitations_credit_id_index` (`credit_id`),
  KEY `credit_invitations_key_index` (`key`),
  KEY `credit_invitations_message_id_index` (`message_id`),
  CONSTRAINT `credit_invitations_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `credit_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `credit_invitations_credit_id_foreign` FOREIGN KEY (`credit_id`) REFERENCES `credits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `credit_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `recurring_id` int(10) unsigned DEFAULT NULL,
  `design_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `last_sent_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `amount` decimal(20,6) NOT NULL,
  `balance` decimal(20,6) NOT NULL,
  `partial` decimal(20,6) DEFAULT NULL,
  `partial_due_date` datetime DEFAULT NULL,
  `last_viewed` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `reminder1_sent` date DEFAULT NULL,
  `reminder2_sent` date DEFAULT NULL,
  `reminder3_sent` date DEFAULT NULL,
  `reminder_last_sent` date DEFAULT NULL,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `tax_data` mediumtext DEFAULT NULL,
  `e_invoice` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credits_company_id_number_unique` (`company_id`,`number`),
  KEY `credits_user_id_foreign` (`user_id`),
  KEY `credits_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `credits_client_id_index` (`client_id`),
  KEY `credits_company_id_index` (`company_id`),
  CONSTRAINT `credits_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `credits_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `symbol` varchar(191) NOT NULL,
  `precision` varchar(191) NOT NULL,
  `thousand_separator` varchar(191) NOT NULL,
  `decimal_separator` varchar(191) NOT NULL,
  `code` varchar(191) NOT NULL,
  `swap_currency_symbol` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(13,6) NOT NULL DEFAULT 1.000000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `date_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `date_formats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(191) NOT NULL,
  `format_moment` varchar(191) NOT NULL,
  `format_dart` varchar(191) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `datetime_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `datetime_formats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(191) NOT NULL,
  `format_moment` varchar(191) NOT NULL,
  `format_dart` varchar(191) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `designs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `designs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `design` mediumtext DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `is_template` tinyint(1) NOT NULL DEFAULT 0,
  `entities` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `designs_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `designs_company_id_index` (`company_id`),
  CONSTRAINT `designs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `url` varchar(191) DEFAULT NULL,
  `preview` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `disk` varchar(191) DEFAULT NULL,
  `hash` varchar(100) DEFAULT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `documentable_id` int(10) unsigned NOT NULL,
  `documentable_type` varchar(191) NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `documents_company_id_index` (`company_id`),
  KEY `documents_documentable_id_documentable_type_deleted_at_index` (`documentable_id`,`documentable_type`,`deleted_at`),
  CONSTRAINT `documents_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `color` varchar(191) NOT NULL DEFAULT '#fff',
  `bank_category_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_categories_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `expense_categories_company_id_index` (`company_id`),
  CONSTRAINT `expense_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `bank_id` int(10) unsigned DEFAULT NULL,
  `invoice_currency_id` int(10) unsigned DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `payment_type_id` int(10) unsigned DEFAULT NULL,
  `recurring_expense_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `amount` decimal(20,6) NOT NULL,
  `foreign_amount` decimal(20,6) NOT NULL,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `transaction_reference` text DEFAULT NULL,
  `should_be_invoiced` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_documents` tinyint(1) NOT NULL DEFAULT 1,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `tax_amount1` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `tax_amount2` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `tax_amount3` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `calculate_tax_by_amount` tinyint(1) NOT NULL DEFAULT 0,
  `e_invoice` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_company_id_number_unique` (`company_id`,`number`),
  KEY `expenses_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_company_id_index` (`company_id`),
  KEY `expenses_invoice_id_deleted_at_index` (`invoice_id`,`deleted_at`),
  CONSTRAINT `expenses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) DEFAULT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateway_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gateway_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `key` varchar(191) NOT NULL,
  `provider` varchar(191) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 10000,
  `site_url` varchar(200) DEFAULT NULL,
  `is_offsite` tinyint(1) NOT NULL DEFAULT 0,
  `is_secure` tinyint(1) NOT NULL DEFAULT 0,
  `fields` longtext DEFAULT NULL,
  `default_gateway_type_id` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateways_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `settings` mediumtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `group_settings_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  CONSTRAINT `group_settings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `industries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_contact_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `signature_ip` text DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_invitations_client_contact_id_invoice_id_unique` (`client_contact_id`,`invoice_id`),
  KEY `invoice_invitations_user_id_foreign` (`user_id`),
  KEY `invoice_invitations_company_id_foreign` (`company_id`),
  KEY `invoice_invitations_deleted_at_invoice_id_company_id_index` (`deleted_at`,`invoice_id`,`company_id`),
  KEY `invoice_invitations_invoice_id_index` (`invoice_id`),
  KEY `invoice_invitations_message_id_index` (`message_id`),
  KEY `invoice_invitations_key_deleted_at_index` (`key`,`deleted_at`),
  CONSTRAINT `invoice_invitations_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_invitations_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `recurring_id` int(10) unsigned DEFAULT NULL,
  `design_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `last_sent_date` date DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `amount` decimal(20,6) NOT NULL,
  `balance` decimal(20,6) NOT NULL,
  `partial` decimal(20,6) DEFAULT NULL,
  `partial_due_date` datetime DEFAULT NULL,
  `last_viewed` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `reminder1_sent` date DEFAULT NULL,
  `reminder2_sent` date DEFAULT NULL,
  `reminder3_sent` date DEFAULT NULL,
  `reminder_last_sent` date DEFAULT NULL,
  `auto_bill_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `auto_bill_tries` smallint(6) NOT NULL DEFAULT 0,
  `is_proforma` tinyint(1) NOT NULL DEFAULT 0,
  `tax_data` mediumtext DEFAULT NULL,
  `e_invoice` mediumtext DEFAULT NULL,
  `sync` text DEFAULT NULL,
  `gateway_fee` decimal(13,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_company_id_number_unique` (`company_id`,`number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  KEY `invoices_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `invoices_client_id_index` (`client_id`),
  KEY `invoices_company_id_index` (`company_id`),
  KEY `invoices_recurring_id_index` (`recurring_id`),
  KEY `invoices_status_id_balance_index` (`status_id`,`balance`),
  KEY `invoices_project_id_deleted_at_index` (`project_id`,`deleted_at`),
  CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `locale` varchar(191) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `licenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(191) DEFAULT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `license_key` varchar(191) DEFAULT NULL,
  `is_claimed` tinyint(1) DEFAULT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `recurring_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `e_invoice_quota` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `licenses_license_key_unique` (`license_key`),
  KEY `licenses_e_invoice_quota_index` (`e_invoice_quota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(128) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_hashes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_hashes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) NOT NULL,
  `fee_total` decimal(16,4) NOT NULL,
  `fee_invoice_id` int(10) unsigned DEFAULT NULL,
  `data` mediumtext NOT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_hashes_payment_id_foreign` (`payment_id`),
  KEY `payment_hashes_hash_index` (`hash`),
  KEY `payment_hashes_fee_invoice_id_index` (`fee_invoice_id`),
  CONSTRAINT `payment_hashes_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_libraries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_libraries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_terms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `num_days` int(11) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_terms_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `payment_terms_user_id_foreign` (`user_id`),
  CONSTRAINT `payment_terms_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payment_terms_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `gateway_type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paymentables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paymentables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(10) unsigned NOT NULL,
  `paymentable_id` int(10) unsigned NOT NULL,
  `amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `refunded` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `paymentable_type` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paymentables_payment_id_foreign` (`payment_id`),
  KEY `paymentables_paymentable_id_index` (`paymentable_id`),
  CONSTRAINT `paymentables_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `client_contact_id` int(10) unsigned DEFAULT NULL,
  `invitation_id` int(10) unsigned DEFAULT NULL,
  `company_gateway_id` int(10) unsigned DEFAULT NULL,
  `gateway_type_id` int(10) unsigned DEFAULT NULL,
  `type_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `refunded` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `applied` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `date` date DEFAULT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `payer_id` varchar(191) DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_manual` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `currency_id` int(10) unsigned NOT NULL,
  `exchange_currency_id` int(10) unsigned DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `refund_meta` text DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_company_id_number_unique` (`company_id`,`number`),
  UNIQUE KEY `payments_company_id_idempotency_key_unique` (`company_id`,`idempotency_key`),
  KEY `payments_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `payments_client_contact_id_foreign` (`client_contact_id`),
  KEY `payments_company_gateway_id_foreign` (`company_gateway_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_company_id_index` (`company_id`),
  KEY `payments_client_id_index` (`client_id`),
  KEY `payments_status_id_index` (`status_id`),
  KEY `payments_transaction_reference_index` (`transaction_reference`),
  KEY `payments_idempotency_key_index` (`idempotency_key`),
  CONSTRAINT `payments_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payments_company_gateway_id_foreign` FOREIGN KEY (`company_gateway_id`) REFERENCES `company_gateways` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `product_key` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cost` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `price` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `quantity` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `in_stock_quantity` int(11) NOT NULL DEFAULT 0,
  `stock_notification` tinyint(1) NOT NULL DEFAULT 1,
  `stock_notification_threshold` int(11) NOT NULL DEFAULT 0,
  `max_quantity` int(10) unsigned DEFAULT NULL,
  `product_image` varchar(191) DEFAULT NULL,
  `tax_id` int(10) unsigned DEFAULT NULL,
  `hash` varchar(191) DEFAULT NULL,
  `sync` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `products_user_id_foreign` (`user_id`),
  KEY `products_company_id_index` (`company_id`),
  KEY `pro_co_us_up_index` (`company_id`,`user_id`,`assigned_user_id`,`updated_at`),
  KEY `products_product_key_company_id_index` (`product_key`,`company_id`),
  CONSTRAINT `products_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `task_rate` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `due_date` date DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `budgeted_hours` decimal(20,6) NOT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `number` varchar(191) DEFAULT NULL,
  `color` varchar(191) NOT NULL DEFAULT '#fff',
  `current_hours` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_company_id_number_unique` (`company_id`,`number`),
  KEY `projects_user_id_foreign` (`user_id`),
  KEY `projects_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `projects_company_id_index` (`company_id`),
  CONSTRAINT `projects_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vendor_contact_id` int(10) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  `signature_ip` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_purchase_unique` (`vendor_contact_id`,`purchase_order_id`),
  KEY `purchase_order_invitations_user_id_foreign` (`user_id`),
  KEY `purchase_order_invitations_company_id_foreign` (`company_id`),
  KEY `vendor_purchase_company_index` (`deleted_at`,`purchase_order_id`,`company_id`),
  KEY `purchase_order_invitations_purchase_order_id_index` (`purchase_order_id`),
  KEY `purchase_order_invitations_key_index` (`key`),
  KEY `purchase_order_invitations_message_id_index` (`message_id`),
  CONSTRAINT `purchase_order_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `purchase_order_invitations_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `purchase_order_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `purchase_order_invitations_vendor_contact_id_foreign` FOREIGN KEY (`vendor_contact_id`) REFERENCES `vendor_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `recurring_id` int(10) unsigned DEFAULT NULL,
  `design_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `last_sent_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `reminder1_sent` date DEFAULT NULL,
  `reminder2_sent` date DEFAULT NULL,
  `reminder3_sent` date DEFAULT NULL,
  `reminder_last_sent` date DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `balance` decimal(20,6) NOT NULL,
  `partial` decimal(20,6) DEFAULT NULL,
  `amount` decimal(20,6) NOT NULL,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `partial_due_date` datetime DEFAULT NULL,
  `last_viewed` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expense_id` int(10) unsigned DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `tax_data` mediumtext DEFAULT NULL,
  `e_invoice` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_orders_user_id_foreign` (`user_id`),
  KEY `purchase_orders_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `purchase_orders_client_id_index` (`client_id`),
  KEY `purchase_orders_company_id_index` (`company_id`),
  KEY `purchase_orders_expense_id_index` (`expense_id`),
  CONSTRAINT `purchase_orders_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `purchase_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `purchase_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quote_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quote_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_contact_id` int(10) unsigned NOT NULL,
  `quote_id` int(10) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `signature_ip` text DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_invitations_client_contact_id_quote_id_unique` (`client_contact_id`,`quote_id`),
  KEY `quote_invitations_user_id_foreign` (`user_id`),
  KEY `quote_invitations_company_id_foreign` (`company_id`),
  KEY `quote_invitations_deleted_at_quote_id_company_id_index` (`deleted_at`,`quote_id`,`company_id`),
  KEY `quote_invitations_quote_id_index` (`quote_id`),
  KEY `quote_invitations_key_index` (`key`),
  KEY `quote_invitations_message_id_index` (`message_id`),
  CONSTRAINT `quote_invitations_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_invitations_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quote_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `recurring_id` int(10) unsigned DEFAULT NULL,
  `design_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `last_sent_date` date DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `amount` decimal(20,6) NOT NULL,
  `balance` decimal(20,6) NOT NULL,
  `partial` decimal(20,6) DEFAULT NULL,
  `partial_due_date` datetime DEFAULT NULL,
  `last_viewed` datetime DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `reminder1_sent` date DEFAULT NULL,
  `reminder2_sent` date DEFAULT NULL,
  `reminder3_sent` date DEFAULT NULL,
  `reminder_last_sent` date DEFAULT NULL,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `tax_data` mediumtext DEFAULT NULL,
  `e_invoice` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotes_company_id_number_unique` (`company_id`,`number`),
  KEY `quotes_user_id_foreign` (`user_id`),
  KEY `quotes_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `quotes_client_id_index` (`client_id`),
  KEY `quotes_company_id_index` (`company_id`),
  KEY `quotes_company_id_updated_at_index` (`company_id`,`updated_at`),
  KEY `quotes_project_id_deleted_at_index` (`project_id`,`deleted_at`),
  CONSTRAINT `quotes_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quotes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `quotes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `bank_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `payment_type_id` int(10) unsigned DEFAULT NULL,
  `recurring_expense_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 1,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_name3` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `should_be_invoiced` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_documents` tinyint(1) NOT NULL DEFAULT 0,
  `transaction_id` varchar(191) DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `calculate_tax_by_amount` tinyint(1) NOT NULL DEFAULT 0,
  `tax_amount1` decimal(20,6) DEFAULT NULL,
  `tax_amount2` decimal(20,6) DEFAULT NULL,
  `tax_amount3` decimal(20,6) DEFAULT NULL,
  `tax_rate1` decimal(20,6) DEFAULT NULL,
  `tax_rate2` decimal(20,6) DEFAULT NULL,
  `tax_rate3` decimal(20,6) DEFAULT NULL,
  `amount` decimal(20,6) DEFAULT NULL,
  `foreign_amount` decimal(20,6) DEFAULT NULL,
  `exchange_rate` decimal(20,6) NOT NULL DEFAULT 1.000000,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `invoice_currency_id` int(10) unsigned DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `transaction_reference` text DEFAULT NULL,
  `frequency_id` int(10) unsigned NOT NULL,
  `last_sent_date` datetime DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `remaining_cycles` int(11) DEFAULT NULL,
  `next_send_date_client` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recurring_expenses_company_id_number_unique` (`company_id`,`number`),
  KEY `recurring_expenses_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `recurring_expenses_user_id_foreign` (`user_id`),
  KEY `recurring_expenses_company_id_index` (`company_id`),
  CONSTRAINT `recurring_expenses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_invoice_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_invoice_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_contact_id` int(10) unsigned NOT NULL,
  `recurring_invoice_id` int(10) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cli_rec` (`client_contact_id`,`recurring_invoice_id`),
  KEY `recurring_invoice_invitations_user_id_foreign` (`user_id`),
  KEY `recurring_invoice_invitations_company_id_foreign` (`company_id`),
  KEY `rec_co_del` (`deleted_at`,`recurring_invoice_id`,`company_id`),
  KEY `recurring_invoice_invitations_recurring_invoice_id_index` (`recurring_invoice_id`),
  KEY `recurring_invoice_invitations_key_index` (`key`),
  CONSTRAINT `recurring_invoice_invitations_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_invoice_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_invoice_invitations_recurring_invoice_id_foreign` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_invoice_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `number` varchar(191) DEFAULT NULL,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `amount` decimal(20,6) NOT NULL,
  `balance` decimal(20,6) NOT NULL,
  `partial` decimal(16,4) DEFAULT NULL,
  `last_viewed` datetime DEFAULT NULL,
  `frequency_id` int(10) unsigned NOT NULL,
  `last_sent_date` datetime DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `remaining_cycles` int(11) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `auto_bill` varchar(191) NOT NULL DEFAULT 'off',
  `auto_bill_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `design_id` int(10) unsigned DEFAULT NULL,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `due_date_days` varchar(191) DEFAULT NULL,
  `partial_due_date` date DEFAULT NULL,
  `exchange_rate` decimal(13,6) NOT NULL DEFAULT 1.000000,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `next_send_date_client` datetime DEFAULT NULL,
  `is_proforma` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recurring_invoices_company_id_number_unique` (`company_id`,`number`),
  KEY `recurring_invoices_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `recurring_invoices_user_id_foreign` (`user_id`),
  KEY `recurring_invoices_client_id_index` (`client_id`),
  KEY `recurring_invoices_company_id_index` (`company_id`),
  KEY `recurring_invoices_status_id_index` (`status_id`),
  CONSTRAINT `recurring_invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_invoices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_quote_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_quote_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_contact_id` int(10) unsigned NOT NULL,
  `recurring_quote_id` int(10) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `transaction_reference` varchar(191) DEFAULT NULL,
  `message_id` varchar(191) DEFAULT NULL,
  `email_error` mediumtext DEFAULT NULL,
  `signature_base64` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL,
  `viewed_date` datetime DEFAULT NULL,
  `opened_date` datetime DEFAULT NULL,
  `email_status` enum('delivered','bounced','spam') DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cli_rec_q` (`client_contact_id`,`recurring_quote_id`),
  KEY `recurring_quote_invitations_user_id_foreign` (`user_id`),
  KEY `recurring_quote_invitations_company_id_foreign` (`company_id`),
  KEY `rec_co_del_q` (`deleted_at`,`recurring_quote_id`,`company_id`),
  KEY `recurring_quote_invitations_recurring_quote_id_index` (`recurring_quote_id`),
  KEY `recurring_quote_invitations_key_index` (`key`),
  CONSTRAINT `recurring_quote_invitations_client_contact_id_foreign` FOREIGN KEY (`client_contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_quote_invitations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_quote_invitations_recurring_quote_id_foreign` FOREIGN KEY (`recurring_quote_id`) REFERENCES `recurring_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_quote_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned NOT NULL,
  `discount` double(8,2) NOT NULL DEFAULT 0.00,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `number` varchar(191) DEFAULT NULL,
  `po_number` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `line_items` mediumtext DEFAULT NULL,
  `backup` mediumtext DEFAULT NULL,
  `footer` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tax_name1` varchar(191) DEFAULT NULL,
  `tax_rate1` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name2` varchar(191) DEFAULT NULL,
  `tax_rate2` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_name3` varchar(191) DEFAULT NULL,
  `tax_rate3` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_taxes` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `balance` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `last_viewed` datetime DEFAULT NULL,
  `frequency_id` int(10) unsigned NOT NULL,
  `last_sent_date` datetime DEFAULT NULL,
  `next_send_date` datetime DEFAULT NULL,
  `remaining_cycles` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `auto_bill` varchar(191) NOT NULL DEFAULT 'off',
  `auto_bill_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `paid_to_date` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `custom_surcharge1` decimal(20,6) DEFAULT NULL,
  `custom_surcharge2` decimal(20,6) DEFAULT NULL,
  `custom_surcharge3` decimal(20,6) DEFAULT NULL,
  `custom_surcharge4` decimal(20,6) DEFAULT NULL,
  `custom_surcharge_tax1` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax2` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax3` tinyint(1) NOT NULL DEFAULT 0,
  `custom_surcharge_tax4` tinyint(1) NOT NULL DEFAULT 0,
  `due_date_days` varchar(191) DEFAULT NULL,
  `exchange_rate` decimal(13,6) NOT NULL DEFAULT 1.000000,
  `partial` decimal(16,4) DEFAULT NULL,
  `partial_due_date` date DEFAULT NULL,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `uses_inclusive_taxes` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `recurring_quotes_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `recurring_quotes_user_id_foreign` (`user_id`),
  KEY `recurring_quotes_client_id_index` (`client_id`),
  KEY `recurring_quotes_company_id_index` (`company_id`),
  KEY `recurring_quotes_status_id_index` (`status_id`),
  CONSTRAINT `recurring_quotes_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_quotes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recurring_quotes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schedulers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedulers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `parameters` mediumtext DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `is_paused` tinyint(1) NOT NULL DEFAULT 0,
  `frequency_id` int(10) unsigned DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `next_run_client` datetime DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `template` varchar(191) NOT NULL,
  `remaining_cycles` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedulers_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  CONSTRAINT `schedulers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sizes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `product_ids` text DEFAULT NULL,
  `frequency_id` int(10) unsigned DEFAULT NULL,
  `auto_bill` text DEFAULT '',
  `promo_code` text DEFAULT '',
  `promo_discount` double(8,2) NOT NULL DEFAULT 0.00,
  `is_amount_discount` tinyint(1) NOT NULL DEFAULT 0,
  `allow_cancellation` tinyint(1) NOT NULL DEFAULT 1,
  `per_seat_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `min_seats_limit` int(10) unsigned NOT NULL,
  `max_seats_limit` int(10) unsigned NOT NULL,
  `trial_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `trial_duration` int(10) unsigned NOT NULL,
  `allow_query_overrides` tinyint(1) NOT NULL DEFAULT 0,
  `allow_plan_changes` tinyint(1) NOT NULL DEFAULT 0,
  `plan_map` text DEFAULT NULL,
  `refund_period` int(10) unsigned DEFAULT NULL,
  `webhook_configuration` mediumtext NOT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `recurring_product_ids` text DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `price` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `promo_price` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `registration_required` tinyint(1) NOT NULL DEFAULT 0,
  `use_inventory_management` tinyint(1) NOT NULL DEFAULT 0,
  `optional_product_ids` text DEFAULT NULL,
  `optional_recurring_product_ids` text DEFAULT NULL,
  `steps` varchar(191) DEFAULT NULL,
  `remaining_cycles` int(11) DEFAULT -1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_company_id_name_unique` (`company_id`,`name`),
  KEY `billing_subscriptions_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  CONSTRAINT `billing_subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `event_id` int(10) unsigned DEFAULT NULL,
  `type_id` int(10) unsigned DEFAULT NULL,
  `log` mediumtext NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_logs_company_id_foreign` (`company_id`),
  KEY `system_logs_client_id_foreign` (`client_id`),
  CONSTRAINT `system_logs_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `system_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `status_sort_order` int(11) DEFAULT NULL,
  `color` varchar(191) NOT NULL DEFAULT '#fff',
  `status_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_statuses_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `task_statuses_user_id_foreign` (`user_id`),
  CONSTRAINT `task_statuses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `task_statuses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned DEFAULT NULL,
  `status_sort_order` int(11) DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_running` tinyint(1) NOT NULL DEFAULT 0,
  `time_log` text DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `rate` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `invoice_documents` tinyint(1) NOT NULL DEFAULT 0,
  `is_date_based` tinyint(1) NOT NULL DEFAULT 0,
  `status_order` int(11) DEFAULT NULL,
  `calculated_start_date` date DEFAULT NULL,
  `hash` varchar(191) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_company_id_number_unique` (`company_id`,`number`),
  KEY `tasks_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `tasks_user_id_foreign` (`user_id`),
  KEY `tasks_invoice_id_foreign` (`invoice_id`),
  KEY `tasks_client_id_foreign` (`client_id`),
  KEY `tasks_company_id_index` (`company_id`),
  KEY `tasks_hash_index` (`hash`),
  CONSTRAINT `tasks_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `rate` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `tax_rates_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `tax_rates_user_id_foreign` (`user_id`),
  KEY `tax_rates_company_id_index` (`company_id`),
  CONSTRAINT `tax_rates_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tax_rates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timezones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `location` varchar(191) NOT NULL,
  `utc_offset` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `payment_id` int(10) unsigned NOT NULL,
  `credit_id` int(10) unsigned NOT NULL,
  `client_balance` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `client_paid_to_date` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `client_credit_balance` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `invoice_balance` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `invoice_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `invoice_partial` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `invoice_paid_to_date` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `invoice_status` int(10) unsigned DEFAULT NULL,
  `payment_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `payment_applied` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `payment_refunded` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `payment_status` int(10) unsigned DEFAULT NULL,
  `paymentables` mediumtext DEFAULT NULL,
  `event_id` int(10) unsigned NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  `payment_request` mediumtext DEFAULT NULL,
  `metadata` mediumtext DEFAULT NULL,
  `credit_balance` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `credit_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `credit_status` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_events_client_id_index` (`client_id`),
  CONSTRAINT `transaction_events_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `first_name` varchar(191) DEFAULT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `ip` varchar(191) DEFAULT NULL,
  `device_token` varchar(191) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `confirmation_code` varchar(191) DEFAULT NULL,
  `theme_id` int(11) DEFAULT NULL,
  `failed_logins` smallint(6) DEFAULT NULL,
  `referral_code` varchar(191) DEFAULT NULL,
  `oauth_user_id` varchar(100) DEFAULT NULL,
  `oauth_user_token` varchar(191) DEFAULT NULL,
  `oauth_provider_id` varchar(191) DEFAULT NULL,
  `google_2fa_secret` text DEFAULT NULL,
  `accepted_terms_version` varchar(191) DEFAULT NULL,
  `avatar` varchar(100) DEFAULT NULL,
  `avatar_width` int(10) unsigned DEFAULT NULL,
  `avatar_height` int(10) unsigned DEFAULT NULL,
  `avatar_size` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `signature` mediumtext DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `oauth_user_refresh_token` text DEFAULT NULL,
  `last_confirmed_email_address` varchar(191) DEFAULT NULL,
  `has_password` tinyint(1) NOT NULL DEFAULT 0,
  `oauth_user_token_expiry` datetime DEFAULT NULL,
  `sms_verification_code` varchar(191) DEFAULT NULL,
  `verified_phone_number` tinyint(1) NOT NULL DEFAULT 0,
  `shopify_user_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` varchar(191) DEFAULT NULL,
  `user_logged_in_notification` tinyint(1) NOT NULL DEFAULT 1,
  `referral_meta` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_oauth_user_id_oauth_provider_id_unique` (`oauth_user_id`,`oauth_provider_id`),
  KEY `users_account_id_index` (`account_id`),
  KEY `users_shopify_user_id_index` (`shopify_user_id`),
  CONSTRAINT `users_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `first_name` varchar(191) DEFAULT NULL,
  `last_name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `send_email` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `confirmation_code` varchar(191) DEFAULT NULL,
  `confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_logins` smallint(6) DEFAULT NULL,
  `oauth_user_id` varchar(100) DEFAULT NULL,
  `oauth_provider_id` int(10) unsigned DEFAULT NULL,
  `google_2fa_secret` varchar(191) DEFAULT NULL,
  `accepted_terms_version` varchar(191) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_type` varchar(255) DEFAULT NULL,
  `avatar_size` varchar(255) DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `token` varchar(191) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `contact_key` varchar(191) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_contacts_oauth_user_id_unique` (`oauth_user_id`),
  UNIQUE KEY `vendor_contacts_oauth_provider_id_unique` (`oauth_provider_id`),
  KEY `vendor_contacts_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `vendor_contacts_user_id_foreign` (`user_id`),
  KEY `vendor_contacts_vendor_id_index` (`vendor_id`),
  KEY `vendor_contacts_company_id_email_deleted_at_index` (`company_id`,`email`,`deleted_at`),
  KEY `vendor_contacts_contact_key(20)_index` (`contact_key`(20)),
  KEY `vendor_contacts_email_index` (`email`),
  CONSTRAINT `vendor_contacts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vendor_contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vendor_contacts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned NOT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `address1` varchar(191) DEFAULT NULL,
  `address2` varchar(191) DEFAULT NULL,
  `city` varchar(191) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `postal_code` varchar(191) DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `private_notes` text DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT 0,
  `vat_number` varchar(191) DEFAULT NULL,
  `transaction_name` varchar(191) DEFAULT NULL,
  `number` varchar(191) DEFAULT NULL,
  `custom_value1` text DEFAULT NULL,
  `custom_value2` text DEFAULT NULL,
  `custom_value3` text DEFAULT NULL,
  `custom_value4` text DEFAULT NULL,
  `vendor_hash` text DEFAULT NULL,
  `public_notes` text DEFAULT NULL,
  `id_number` varchar(191) DEFAULT NULL,
  `language_id` int(10) unsigned DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `classification` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendors_company_id_number_unique` (`company_id`,`number`),
  KEY `vendors_company_id_deleted_at_index` (`company_id`,`deleted_at`),
  KEY `vendors_user_id_foreign` (`user_id`),
  KEY `vendors_country_id_foreign` (`country_id`),
  KEY `vendors_currency_id_foreign` (`currency_id`),
  CONSTRAINT `vendors_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vendors_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `vendors_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `vendors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhooks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `event_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `target_url` text NOT NULL,
  `format` enum('JSON','UBL') NOT NULL DEFAULT 'JSON',
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `deleted_at` timestamp(6) NULL DEFAULT NULL,
  `rest_method` text DEFAULT NULL,
  `headers` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_company_id_foreign` (`company_id`),
  KEY `subscriptions_event_id_company_id_index` (`event_id`,`company_id`),
  CONSTRAINT `subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_13_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_11_10_115926_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2020_03_05_123315_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2020_04_08_234530_add_is_deleted_column_to_company_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2020_05_13_035355_add_google_refresh_token_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2020_07_05_084934_company_too_large_attribute',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2020_07_08_065301_add_token_id_to_activity_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2020_07_21_112424_update_enabled_modules_value',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2020_07_28_104218_shop_token',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2020_08_04_080851_add_is_deleted_to_group_settings',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2020_08_11_221627_add_is_deleted_flag_to_client_gateway_token_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2020_08_13_095946_remove_photo_design',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2020_08_13_212702_add_reminder_sent_fields_to_entity_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2020_08_18_140557_add_is_public_to_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2020_09_22_205113_id_number_fields_for_missing_entities',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2020_09_27_215800_update_gateway_table_visible_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2020_10_11_211122_vendor_schema_update',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2020_10_12_204517_project_number_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2020_10_14_201320_project_ids_to_entities',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2020_10_19_101823_project_name_unique_removal',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2020_10_21_222738_expenses_nullable_assigned_user',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2020_10_22_204900_company_table_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2020_10_27_021751_tasks_invoice_documents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2020_10_28_224711_status_sort_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2020_10_28_225022_assigned_user_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2020_10_29_001541_vendors_phone_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2020_10_29_093836_change_start_time_column_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2020_10_29_204434_tasks_table_project_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2020_10_29_210402_change_default_show_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2020_10_30_084139_change_expense_currency_id_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2020_11_01_031750_drop_migrating_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2020_11_03_200345_company_gateway_fields_refactor',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2020_11_08_212050_custom_fields_for_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2020_11_12_104413_company_gateway_rename_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2020_11_15_203755_soft_delete_paymentables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2020_12_14_114722_task_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2020_12_17_104033_add_enable_product_discount_field_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2020_12_20_005609_change_products_table_cost_resolution',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2020_12_23_220648_remove_null_values_in_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2021_01_03_215053_update_canadian_dollar_symbol',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2021_01_05_013203_improve_decimal_resolution',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2021_01_07_023350_update_singapore_dollar_symbol',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2021_01_08_093324_expenses_table_additional_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2021_01_11_092056_fix_company_settings_url',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2021_01_17_040331_change_custom_surcharge_column_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2021_01_23_044502_scheduler_is_running_check',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2021_01_24_052645_add_paid_to_date_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2021_01_25_095351_add_number_field_to_clients_and_vendors',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2021_01_29_121502_add_permission_changed_timestamp',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2021_02_15_214724_additional_company_properties',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2021_02_19_212722_email_last_confirmed_email_address_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2021_02_25_205901_enum_invitations_email_status',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2021_02_27_091713_add_invoice_task_datelog_property',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2021_03_03_230941_add_has_password_field_to_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2021_03_08_123729_create_billing_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2021_03_08_205030_add_russian_lang',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2021_03_09_132242_add_currency_id_to_billing_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2021_03_18_113704_change_2fa_column_from_varchar_to_text',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2021_03_19_221024_add_unique_constraints_on_all_entities',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2021_03_20_033751_add_invoice_id_to_client_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2021_03_23_233844_add_nullable_constraint_to_recurring_invoice_id',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2021_03_25_082025_refactor_billing_scriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2021_03_26_201148_add_price_column_to_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2021_04_01_093128_modify_column_on_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2021_04_05_115345_add_trial_duration_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2021_04_05_213802_add_rest_fields_to_webhooks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2021_04_06_131028_create_licenses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2021_04_12_095424_stripe_connect_gateway',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2021_04_13_013424_add_subscription_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2021_04_22_110240_add_property_to_checkout_gateway_config',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2021_04_29_085418_add_number_years_active_to_company_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2021_05_03_152940_make_braintree_provider_visible',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2021_05_04_231430_add_task_property_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2021_05_05_014713_activate_we_pay',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2021_05_10_041528_add_recurring_invoice_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2021_05_27_105157_add_tech_design',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2021_05_30_100933_make_documents_assigned_user_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2021_06_10_221012_add_ninja_portal_column_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2021_06_24_095942_payments_table_currency_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2021_07_10_085821_activate_payfast_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2021_07_19_074503_set_invoice_task_datelog_true_in_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2021_07_20_095537_activate_paytrace_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2021_07_21_213344_change_english_languages_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2021_07_21_234227_activate_eway_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2021_08_03_115024_activate_mollie_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2021_08_05_235942_add_zelle_payment_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2021_08_07_222435_add_markdown_enabled_column_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2021_08_10_034407_add_more_languages',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2021_08_14_054458_square_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2021_08_18_220124_use_comma_as_decimal_place_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2021_08_23_101529_recurring_expenses_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2021_08_25_093105_report_include_drafts_in_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2021_09_05_101209_update_braintree_gateway',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2021_09_20_233053_set_square_test_mode_boolean',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2021_09_23_100629_add_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2021_09_24_201319_add_mollie_bank_transfer_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2021_09_24_211504_add_kbc_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2021_09_24_213858_add_bancontact_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2021_09_28_154647_activate_gocardless_payment_driver',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2021_09_29_190258_add_required_client_registration_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2021_10_04_134908_add_ideal_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2021_10_06_044800_updated_bold_and_modern_designs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2021_10_07_141737_razorpay',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2021_10_07_155410_add_hosted_page_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2021_10_15_00000_stripe_payment_gateways',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2021_10_16_135200_add_direct_debit_to_payment_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2021_10_19_142200_add_gateway_type_for_direct_debit',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2021_10_20_005529_add_filename_to_backups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2021_11_08_131308_onboarding',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2021_11_09_115919_update_designs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2021_11_10_184847_add_is_migrate_column_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2021_11_11_163121_add_instant_bank_transfer',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2021_12_20_095542_add_serbian_language_translations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2022_01_02_022421_add_slovak_language',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2022_01_06_061231_add_app_domain_id_to_gateways_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2022_01_18_004856_add_estonian_language',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2022_01_19_085907_add_platform_column_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2022_01_19_232436_add_kyd_currency',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2022_01_27_223617_add_client_count_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2022_02_06_091629_add_client_currency_conversion_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2022_02_25_015411_update_stripe_apple_domain_config',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2022_03_09_053508_transaction_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2022_03_24_090728_markdown_email_enabled_wysiwyg_editor',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2022_03_29_014025_reverse_apple_domain_for_hosted',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2022_04_14_121548_forte_payment_gateway',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2022_04_22_115838_client_settings_parse_for_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2022_04_26_032252_convert_custom_fields_column_from_varchar_to_text',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2022_05_08_004937_heal_stripe_gateway_configuration',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2022_05_12_56879_add_stripe_klarna',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2022_05_16_224917_add_auto_bill_tries_to_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2022_05_18_055442_update_custom_value_four_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2022_05_18_162152_create_scheduled_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2022_05_18_162443_create_schedulers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2022_05_23_050754_drop_redundant_column_show_production_description_dropdown',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2022_05_28_234651_create_purchase_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2022_05_30_181109_drop_scheduled_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2022_05_30_184320_add_job_related_fields_to_schedulers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2022_05_31_101504_inventory_management_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2022_06_01_215859_set_recurring_client_timestamp',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2022_06_01_224339_create_purchase_order_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2022_06_10_030503_set_account_flag_for_react',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2022_06_16_025156_add_react_switching_flag',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2022_06_17_082627_change_refresh_token_column_size',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2022_06_21_104350_fixes_for_description_in_pdf_designs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2022_06_22_090547_set_oauth_expiry_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2022_06_24_141018_upgrade_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2022_06_30_000126_add_flag_to_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2022_07_06_080127_add_purchase_order_to_expense',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2022_07_09_235510_add_index_to_payment_hash',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2022_07_12_45766_add_matomo',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2022_07_18_033756_fixes_for_date_formats_table_react',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2022_07_21_023805_add_hebrew_language',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2022_07_26_091216_add_sms_verification_to_hosted_account',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2022_07_28_232340_enabled_expense_tax_rates_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2022_07_29_091235_correction_for_companies_table_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2022_08_05_023357_bank_integration',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2022_08_11_011534_licenses_table_for_self_host',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2022_08_24_215917_invoice_task_project_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2022_08_26_232500_add_email_status_column_to_purchase_order_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2022_08_28_210111_add_index_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2022_09_05_024719_update_designs_for_tech_template',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2022_09_07_101731_add_reporting_option_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2022_09_21_012417_add_threeds_to_braintree',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2022_09_30_235337_add_idempotency_key_to_payments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2022_10_05_205645_add_indexes_to_client_hash',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2022_10_06_011344_add_key_to_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2022_10_07_065455_add_key_to_company_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2022_10_10_070137_add_documentable_index',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2022_10_27_044909_add_user_sms_verification_code',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2022_11_02_063742_add_verified_number_flag_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2022_11_04_013539_disabled_upstream_bank_integrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2022_11_06_215526_drop_html_backups_column_from_backups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2022_11_13_034143_bank_transaction_rules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2022_11_16_093535_calmness_design',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2022_11_22_215618_lock_tasks_when_invoiced',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2022_11_30_063229_add_payment_id_to_bank_transaction_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2022_12_07_024625_add_properties_to_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2022_12_14_004639_vendor_currency_update',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2022_12_20_063038_set_proforma_invoice_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2023_01_12_125540_set_auto_bill_on_regular_invoice_setting',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2022_16_12_54687_add_stripe_bacs',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2023_01_27_023127_update_design_templates',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2023_02_02_062938_add_additional_required_fields_gateways',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2023_02_05_042351_add_foreign_key_for_vendors',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2023_02_07_114011_add_additional_product_fields',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2023_02_14_064135_create_react_settings_column_company_user_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2023_02_28_064453_update_designs',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2023_02_28_200056_add_visible_prop_to_companies_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2023_03_09_121033_add_payment_balance_to_clients_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2023_03_10_100629_add_currencies',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2023_03_13_156872_add_e_invoice_type_to_clients_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2023_03_17_012309_add_proforma_flag_for_recurring_invoices',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2023_03_21_053933_tax_calculations_for_invoices',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2023_03_24_054758_add_client_is_exempt_from_taxes',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2023_04_20_215159_drop_e_invoice_type_column',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2023_04_27_045639_add_kmher_language',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2023_05_03_023956_add_shopify_user_id',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2023_05_15_103212_e_invoice_ssl_storage',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2023_06_04_064713_project_and_task_columns_for_company_model',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2023_06_13_220252_add_hungarian_translations',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2023_06_20_123355_add_paypal_rest_payment_driver',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2023_07_06_063512_update_designs',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2023_07_08_000314_add_french_swiss_translations',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2023_07_12_074829_add_thai_baht_currency_symbol',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2023_07_18_214607_add_start_date_column_to_tasks',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2023_07_22_234329_change_currency_format_for_indonesian_rupiah',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2023_08_06_070205_create_view_dashboard_permission_migration',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2023_08_08_212710_add_signature_ip_address_to_purchase_order_invitations',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2023_08_09_224955_add_nicaragua_currency',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2023_09_11_003230_add_client_and_company_classifications',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2023_09_21_042010_add_template_flag_to_designs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2023_10_01_102220_add_language_id_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2023_10_08_092508_add_refund_meta_and_category_to_payments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2023_10_10_083024_add_ariary_currency',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2023_10_15_204204_add_paypal_ppcp',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2023_10_18_061415_add_user_notification_suppression',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2023_11_26_082959_add_bank_integration_id',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2023_11_27_095042_add_hash_and_meta_columns_to_tasks_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2023_11_30_042431_2023_11_30_add_payment_visibility',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2024_01_09_084515_product_cost_field_population',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2024_01_10_071427_normalize_product_cost_types',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2024_01_10_155555_add_bank_transaction_nordigen_field',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2024_01_12_073629_laos_currency_translation',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2024_01_29_080555_2024_01_29_update_timezones_naming',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2024_02_06_204031_correction_for_krw_currency',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2024_02_16_011055_smtp_configuration',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2024_02_28_180250_add_steps_to_subscriptions',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2024_03_07_195116_add_tax_data_to_quotes',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2024_03_14_201844_adjust_discount_column_max_resolution',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2024_03_24_200109_new_currencies_03_24',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2024_04_24_064301_optional_display_required_fields_payment_gateways',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2024_05_02_030103_2024_05_02_update_timezones',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2024_05_03_145535_btcpay_gateway',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2024_05_19_215103_2024_05_20_einvoice_columns',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2024_05_26_210407_2024_05_28_kwd_precision',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2024_06_02_083543_2024_06_01_add_einvoice_to_client_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2024_06_04_123926_2024_06_04_fixes_for_btc_migration',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2024_06_08_043343_2024_06_08__i_s_k_currency_precision',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2024_06_19_015127_2024_06_19_referral_meta_data',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2023_12_10_110951_inbound_mail_parsing',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2024_06_11_231143_add_rotessa_gateway',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2024_06_23_040253_2024-06-23_indexesforinvoiceid_payment_hashes',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2024_07_10_043241_2024_07_10_invoice_id_index_on_projects_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2024_07_16_231556_2024_07_17_add_dubai_timezone',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2024_07_29_235430_2024_30_07_tax_model_migration',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2024_08_02_144614_alter_companies_quickbooks',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2024_08_04_225558_tax_model_migration_v2',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2024_08_21_001832_add_einvoice_option_license',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2024_08_26_055523_add_qb_product_hash',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2024_08_27_230111_blockonomics_gateway',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2024_09_06_042040_cba_powerboard',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2024_09_15_022436_add_autonomous_es_regions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2024_09_16_221343_add_remaining_cycles_to_subscriptions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2024_09_21_062105_2024_09_21_add_vn_lang',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2024_09_22_084749_2024_09_23_add_sync_column_for_qb',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2024_09_29_221552_add_gateway_fee_column',4);
