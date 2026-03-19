-- =============================================================
-- Views e tabelas MySQL compatíveis com OpenCardB2B (SECTRA ERP)
-- Mapeia tabelas Magento 2 → formato OpenCart 3.x
--
-- O módulo OpenCardB2B do SECTRA lê oc_order, oc_order_product,
-- oc_pre_registration e grava no VE_PEDIDO com STATUS='W' (Ped. Web)
--
-- Estrutura baseada no OpenCart 3.x original (DB antigo em 35.215.119.89)
-- Colunas, tipos e formatos batem com o schema real do OpenCart
--
-- OFFSET: +200000 em todos os IDs de pedido para evitar conflito
-- com IDs antigos (último order_id no OpenCart: ~160)
--
-- Mapeamentos:
--   oc_customer_id_map: old OpenCart customer_id → Magento customer_id (por CNPJ)
--   oc_product_id_map:  old OpenCart product_id → Magento SKU (por SKU exato)
--
-- Conexão: usuário MySQL 'sectra' criado via painel Hostinger
-- Config SECTRA (seção 24.05): host=72.61.94.22, db=magento, user=sectra
--
-- PERMISSÕES necessárias para o usuário 'sectra':
--   GRANT SELECT ON magento.* TO 'sectra'@'%';
--   GRANT INSERT ON magento.oc_order_history TO 'sectra'@'%';
--   GRANT INSERT ON magento.oc_order_imported TO 'sectra'@'%';
--   GRANT INSERT, UPDATE ON magento.oc_pre_registration TO 'sectra'@'%';
--
-- Fluxo de deduplicação:
--   1. SECTRA lê pedidos de oc_order (VIEW) WHERE order_status_id = 1
--   2. SECTRA importa e faz INSERT INTO oc_order_history
--   3. Trigger trg_order_history_auto_import auto-insere em oc_order_imported
--   4. Pedido desaparece da VIEW oc_order (NOT EXISTS clause)
--   5. Se SECTRA tentar UPDATE oc_order SET order_status_id=0 → falha (VIEW read-only)
--      mas isso é inofensivo pois o trigger já cuidou da deduplicação
-- =============================================================

-- ---------------------------------------------------------------
-- 1. Tabela estática: oc_order_status
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_order_status (
    order_status_id INT NOT NULL,
    language_id INT NOT NULL DEFAULT 1,
    name VARCHAR(32) NOT NULL,
    PRIMARY KEY (order_status_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_order_status (order_status_id, language_id, name) VALUES
(1,  1, 'Pendente'),
(2,  1, 'Processando'),
(3,  1, 'Enviado'),
(5,  1, 'Completo'),
(7,  1, 'Cancelado'),
(10, 1, 'Negado'),
(11, 1, 'Devolvido'),
(15, 1, 'Aguardando Pagamento');

-- ---------------------------------------------------------------
-- 2. Tabela estática: oc_language
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_language (
    language_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(32) NOT NULL,
    code VARCHAR(5) NOT NULL,
    locale VARCHAR(255) NOT NULL,
    image VARCHAR(64) NOT NULL,
    directory VARCHAR(32) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_language (language_id, name, code, locale, image, directory, sort_order, status) VALUES
(1, 'English',        'en-gb', 'en_US.UTF-8', 'gb.png', 'english',       2, 1),
(2, 'Português BR',   'pt-br', 'pt_BR.UTF-8', 'br.png', 'portuguese-br', 1, 1);

-- ---------------------------------------------------------------
-- 3. Tabela estática: oc_currency
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_currency (
    currency_id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(32) NOT NULL,
    code VARCHAR(3) NOT NULL,
    symbol_left VARCHAR(12) NOT NULL DEFAULT '',
    symbol_right VARCHAR(12) NOT NULL DEFAULT '',
    decimal_place CHAR(1) NOT NULL DEFAULT '2',
    value DECIMAL(15,8) NOT NULL DEFAULT 1.00000000,
    status TINYINT(1) NOT NULL DEFAULT 1,
    date_modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (currency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_currency (currency_id, title, code, symbol_left, symbol_right, decimal_place, value, status) VALUES
(2, 'Real', 'BRL', 'R$ ', '', '2', 1.00000000, 1);

-- ---------------------------------------------------------------
-- 4. Tabela estática: oc_country
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_country (
    country_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL,
    iso_code_2 VARCHAR(2) NOT NULL,
    iso_code_3 VARCHAR(3) NOT NULL,
    address_format TEXT NOT NULL,
    postcode_required TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (country_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_country (country_id, name, iso_code_2, iso_code_3, address_format, postcode_required, status) VALUES
(30, 'Brasil', 'BR', 'BRA', '', 1, 1);

-- ---------------------------------------------------------------
-- 5. Tabela estática: oc_customer_group
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_customer_group (
    customer_group_id INT NOT NULL AUTO_INCREMENT,
    approval INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (customer_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_customer_group (customer_group_id, approval, sort_order) VALUES
(1, 0, 1),
(2, 1, 2);

-- ---------------------------------------------------------------
-- 6. Tabela estática: oc_customer_group_description
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_customer_group_description (
    customer_group_id INT NOT NULL,
    language_id INT NOT NULL,
    name VARCHAR(32) NOT NULL,
    description TEXT,
    PRIMARY KEY (customer_group_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_customer_group_description (customer_group_id, language_id, name, description) VALUES
(1, 2, 'Pessoa física',   NULL),
(2, 2, 'Pessoa jurídica',  NULL);

-- ---------------------------------------------------------------
-- 7. Tabela estática: oc_order_history (vazia, OpenCardB2B pode gravar)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_order_history (
    order_history_id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_status_id INT NOT NULL,
    notify TINYINT(1) NOT NULL DEFAULT 0,
    comment TEXT NOT NULL,
    date_added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_history_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger: quando SECTRA insere em oc_order_history (após importar pedido),
-- auto-insere em oc_order_imported para remover o pedido da VIEW oc_order.
-- No OpenCart original, SECTRA fazia UPDATE oc_order SET order_status_id=0.
-- Como oc_order é VIEW (read-only, não updatable), usamos este trigger.
DROP TRIGGER IF EXISTS trg_order_history_auto_import;
DELIMITER //
CREATE TRIGGER trg_order_history_auto_import
AFTER INSERT ON oc_order_history
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO oc_order_imported (order_id, imported_at)
    VALUES (NEW.order_id, NOW());
END//
DELIMITER ;

-- ---------------------------------------------------------------
-- 8. Tabela estática: oc_setting (config da loja — OpenCardB2B lê daqui)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_setting (
    setting_id INT NOT NULL AUTO_INCREMENT,
    store_id INT NOT NULL DEFAULT 0,
    code VARCHAR(128) NOT NULL DEFAULT '',
    `key` VARCHAR(128) NOT NULL DEFAULT '',
    value TEXT NOT NULL,
    serialized TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (setting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_setting (store_id, code, `key`, value, serialized) VALUES
(0, 'config', 'config_name',                   'AWA MOTOS', 0),
(0, 'config', 'config_owner',                  'AWA MOTOS', 0),
(0, 'config', 'config_address',                'R. Lavineo de Arruda Falcão, 1272\nJardim Cruzeiro do Sul CEP: 14808-390\nAraraquara - SP', 0),
(0, 'config', 'config_email',                  'sac@awamotos.com.br', 0),
(0, 'config', 'config_telephone',              '(16) 3301-1890', 0),
(0, 'config', 'config_fax',                    '', 0),
(0, 'config', 'config_country_id',             '30', 0),
(0, 'config', 'config_zone_id',                '455', 0),
(0, 'config', 'config_language',               'pt-br', 0),
(0, 'config', 'config_admin_language',          'pt-br', 0),
(0, 'config', 'config_currency',               'BRL', 0),
(0, 'config', 'config_currency_auto',           '0', 0),
(0, 'config', 'config_tax',                    '0', 0),
(0, 'config', 'config_tax_default',            'shipping', 0),
(0, 'config', 'config_tax_customer',           'shipping', 0),
(0, 'config', 'config_customer_group_id',       '2', 0),
(0, 'config', 'config_customer_group_display',  '["2","1"]', 1),
(0, 'config', 'config_customer_price',          '1', 0),
(0, 'config', 'config_customer_online',         '1', 0),
(0, 'config', 'config_customer_activity',       '1', 0),
(0, 'config', 'config_customer_search',         '1', 0),
(0, 'config', 'config_account_id',              '3', 0),
(0, 'config', 'config_invoice_prefix',          'MAG-', 0),
(0, 'config', 'config_order_status_id',         '1', 0),
(0, 'config', 'config_processing_status',       '["2"]', 1),
(0, 'config', 'config_complete_status',         '["5","3"]', 1),
(0, 'config', 'config_stock_display',           '0', 0),
(0, 'config', 'config_stock_warning',           '0', 0),
(0, 'config', 'config_stock_checkout',          '1', 0),
(0, 'config', 'config_checkout_guest',          '0', 0),
(0, 'config', 'config_checkout_id',             '5', 0),
(0, 'config', 'config_api_id',                  '0', 0),
(0, 'config', 'config_api_rest',                '1', 0),
(0, 'config', 'config_comment',                 '', 0),
(0, 'config', 'config_meta_title',              'AWA MOTOS', 0),
(0, 'config', 'config_meta_description',        'AWA Motos - Acessórios para Motos desde 1997', 0),
(0, 'config', 'config_meta_keyword',            '', 0),
(0, 'config', 'config_seo_url',                 '1', 0),
(0, 'config', 'config_secure',                  '1', 0),
(0, 'config', 'config_shared',                  '0', 0),
(0, 'config', 'config_maintenance',             '0', 0),
(0, 'config', 'config_password',                '1', 0),
(0, 'config', 'config_encryption',              '', 0),
(0, 'config', 'config_compression',             '0', 0),
(0, 'config', 'config_error_display',           '0', 0),
(0, 'config', 'config_error_log',               '1', 0),
(0, 'config', 'config_error_filename',          'error.log', 0);

-- ---------------------------------------------------------------
-- 9. Tabela: oc_zone (27 estados brasileiros com IDs do OpenCart)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_zone (
    zone_id INT NOT NULL,
    country_id INT NOT NULL DEFAULT 30,
    name VARCHAR(128) NOT NULL,
    code VARCHAR(32) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_zone (zone_id, country_id, name, code) VALUES
(440, 30, 'Acre',                'AC'),
(441, 30, 'Alagoas',             'AL'),
(442, 30, 'Amapá',               'AP'),
(443, 30, 'Amazonas',            'AM'),
(444, 30, 'Bahia',               'BA'),
(445, 30, 'Ceará',               'CE'),
(446, 30, 'Distrito Federal',    'DF'),
(447, 30, 'Espírito Santo',      'ES'),
(448, 30, 'Goiás',               'GO'),
(449, 30, 'Maranhão',            'MA'),
(450, 30, 'Mato Grosso',         'MT'),
(451, 30, 'Mato Grosso do Sul',  'MS'),
(452, 30, 'Minas Gerais',        'MG'),
(453, 30, 'Pará',                'PA'),
(454, 30, 'Paraíba',             'PB'),
(455, 30, 'Paraná',              'PR'),
(456, 30, 'Pernambuco',          'PE'),
(457, 30, 'Piauí',               'PI'),
(458, 30, 'Rio de Janeiro',      'RJ'),
(459, 30, 'Rio Grande do Norte', 'RN'),
(460, 30, 'Rio Grande do Sul',   'RS'),
(461, 30, 'Rondônia',            'RO'),
(462, 30, 'Roraima',             'RR'),
(463, 30, 'Santa Catarina',      'SC'),
(464, 30, 'São Paulo',           'SP'),
(465, 30, 'Sergipe',             'SE'),
(466, 30, 'Tocantins',           'TO');

-- ---------------------------------------------------------------
-- 10. Tabela: oc_zone_mapping (Magento region_id → OpenCart zone_id)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_zone_mapping (
    magento_region_id INT NOT NULL,
    oc_zone_id INT NOT NULL,
    PRIMARY KEY (magento_region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_zone_mapping (magento_region_id, oc_zone_id) VALUES
(485, 440), -- AC
(486, 441), -- AL
(487, 442), -- AP
(488, 443), -- AM
(489, 444), -- BA
(490, 445), -- CE
(491, 447), -- ES
(492, 448), -- GO
(493, 449), -- MA
(494, 450), -- MT
(495, 451), -- MS
(496, 452), -- MG
(497, 453), -- PA
(498, 454), -- PB
(499, 455), -- PR
(500, 456), -- PE
(501, 457), -- PI
(502, 458), -- RJ
(503, 459), -- RN
(504, 460), -- RS
(505, 461), -- RO
(506, 462), -- RR
(507, 463), -- SC
(508, 464), -- SP
(509, 465), -- SE
(510, 466), -- TO
(511, 446); -- DF

-- ---------------------------------------------------------------
-- 11. Tabelas: oc_custom_field (campos customizados do OpenCart)
--     SECTRA consulta essas tabelas para interpretar o JSON custom_field
--     Query: SELECT custom_field_id, code FROM oc_custom_field WHERE code = 'cnpj'
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_custom_field (
    custom_field_id INT NOT NULL AUTO_INCREMENT,
    type VARCHAR(32) NOT NULL,
    code VARCHAR(64) NOT NULL DEFAULT '',
    value TEXT NOT NULL,
    validation VARCHAR(255) NOT NULL DEFAULT '',
    location VARCHAR(32) NOT NULL DEFAULT 'account',
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (custom_field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_custom_field (custom_field_id, type, code, value, validation, location, status, sort_order) VALUES
(1, 'text', 'company_name', '', '', 'account', 1, 4),
(2, 'text', 'cpf',          '', '', 'account', 1, 4),
(3, 'text', 'ie',           '', '', 'account', 1, 5),
(4, 'text', 'number',       '', '', 'address', 0, 3),
(5, 'text', 'complement',   '', '', 'address', 1, 4),
(6, 'text', 'cnpj',         '', '', 'account', 1, 3);

CREATE TABLE IF NOT EXISTS oc_custom_field_description (
    custom_field_id INT NOT NULL,
    language_id INT NOT NULL,
    name VARCHAR(128) NOT NULL,
    PRIMARY KEY (custom_field_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_custom_field_description (custom_field_id, language_id, name) VALUES
(1, 2, 'Razão Social'),
(2, 2, 'CPF'),
(3, 2, 'IE'),
(4, 2, 'Número'),
(5, 2, 'Complemento'),
(6, 2, 'CNPJ');

CREATE TABLE IF NOT EXISTS oc_custom_field_customer_group (
    custom_field_id INT NOT NULL,
    customer_group_id INT NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (custom_field_id, customer_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oc_custom_field_customer_group (custom_field_id, customer_group_id, required) VALUES
(1, 2, 0),
(2, 1, 1),
(3, 2, 1),
(5, 2, 0),
(6, 1, 0),
(6, 2, 1);

CREATE TABLE IF NOT EXISTS oc_custom_field_value (
    custom_field_value_id INT NOT NULL AUTO_INCREMENT,
    custom_field_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (custom_field_value_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS oc_custom_field_value_description (
    custom_field_value_id INT NOT NULL,
    language_id INT NOT NULL,
    custom_field_id INT NOT NULL,
    name VARCHAR(128) NOT NULL,
    PRIMARY KEY (custom_field_value_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 12. Tabela: oc_customer_id_map (mapeamento OpenCart → Magento por CNPJ)
--     Dados populados via script que cruza clientes antigos do OpenCart
--     com clientes do Magento por CNPJ. 1304 registros, 1196 mapeados.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_customer_id_map (
    old_oc_customer_id INT NOT NULL,
    old_email VARCHAR(255) NOT NULL DEFAULT '',
    old_cnpj VARCHAR(20) NOT NULL DEFAULT '',
    magento_customer_id INT DEFAULT NULL,
    PRIMARY KEY (old_oc_customer_id),
    KEY idx_cnpj (old_cnpj),
    KEY idx_email (old_email),
    KEY idx_magento (magento_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados populados separadamente via export do OpenCart antigo (35.215.119.89)
-- Script: mysql -h 35.215.119.89 -u usytxntq7elcj -pkftkqyxkga3r dbae2ldwuk22sa
-- Query: SELECT customer_id, email, cnpj FROM oc_customer (via custom_field JSON key "6")

-- ---------------------------------------------------------------
-- 13. Tabela: oc_product_id_map (mapeamento SKU Magento → product_id OpenCart)
--     SECTRA usa o product_id do OpenCart antigo para buscar no banco interno.
--     Mapeados por SKU exato. 476 produtos mapeados.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_product_id_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    magento_entity_id INT UNSIGNED NULL,
    magento_sku VARCHAR(64) NOT NULL,
    old_oc_product_id BIGINT NOT NULL,
    old_oc_sku VARCHAR(64) NOT NULL,
    UNIQUE KEY uk_magento_sku (magento_sku),
    KEY idx_old_oc_pid (old_oc_product_id),
    KEY idx_magento_eid (magento_entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dados populados via script que cruza SKUs do OpenCart antigo com Magento.
-- Mapeamento especial: SKU '41544' (Bauleto 41L genérico) → 98050005 (410 PT)

-- ---------------------------------------------------------------
-- 13b. Tabela: oc_order_imported (controle de pedidos já importados pelo SECTRA)
--      Após SECTRA importar um pedido, INSERT aqui para excluí-lo da view oc_order.
--      No OpenCart original, SECTRA fazia UPDATE oc_order SET order_status_id=0.
--      Como oc_order é VIEW (read-only), usamos esta tabela auxiliar.
--      O usuário sectra precisa de: GRANT INSERT ON magento.oc_order_imported TO 'sectra'@'%';
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_order_imported (
    order_id INT NOT NULL PRIMARY KEY,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 14. Tabela: oc_pre_registration (clientes prospect para SECTRA)
--     SECTRA importa prospects via: Comercial→Ferramentas→Integração AWA→Importar Clientes Prospect
--     Tabela real (não VIEW) pois SECTRA pode gravar/atualizar registros.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oc_pre_registration (
    customer_id INT NOT NULL AUTO_INCREMENT,
    customer_group_id INT NOT NULL,
    store_id INT NOT NULL DEFAULT 0,
    language_id INT NOT NULL,
    firstname VARCHAR(32) NOT NULL,
    lastname VARCHAR(32) NOT NULL,
    email VARCHAR(96) NOT NULL,
    telephone VARCHAR(32) NOT NULL,
    fax VARCHAR(32) NOT NULL DEFAULT '',
    `password` VARCHAR(255) NOT NULL DEFAULT '',
    salt VARCHAR(9) NOT NULL DEFAULT '',
    cart TEXT,
    wishlist TEXT,
    newsletter TINYINT(1) NOT NULL DEFAULT 0,
    address_id INT NOT NULL DEFAULT 0,
    custom_field TEXT NOT NULL,
    ip VARCHAR(40) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 0,
    safe TINYINT(1) NOT NULL DEFAULT 0,
    token TEXT NOT NULL,
    code VARCHAR(40) NOT NULL DEFAULT '',
    date_added DATETIME NOT NULL,
    PRIMARY KEY (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 15. VIEW: oc_order (pedidos Magento → formato OpenCart)
--
--     Mapeamentos:
--     - customer_id: usa oc_customer_id_map para resolver ID antigo do OpenCart
--       Se não encontrar, fallback para customer_id + 200000
--     - zone_id: join por NOME do estado (não por region_id) com COLLATE fix
--     - custom_field: CNPJ sem formatação no key "6"
--     - customer_group_id: fixo 2 (PJ)
--     - Filtro: apenas pedidos com customer_id e state IN (new, pending_payment, processing)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW oc_order AS
SELECT
    so.entity_id + 200000 AS order_id,
    0 AS invoice_no,
    'MAG-' AS invoice_prefix,
    so.store_id AS store_id,
    'AWA MOTOS' AS store_name,
    'https://awamotos.com.br/' AS store_url,
    COALESCE(m.old_oc_customer_id, so.customer_id + 200000) AS customer_id,
    2 AS customer_group_id,
    COALESCE(so.customer_firstname, '') AS firstname,
    COALESCE(so.customer_lastname, '') AS lastname,
    COALESCE(so.customer_email, '') AS email,
    COALESCE(ba.telephone, '') AS telephone,
    '' AS fax,
    CONCAT(
        '{"6":"',
        REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(so.customer_taxvat, ''), '.', ''), '/', ''), '-', ''), ' ', ''),
        '","2":"","3":"","1":""}'
    ) AS custom_field,
    COALESCE(ba.firstname, so.customer_firstname, '') AS payment_firstname,
    COALESCE(ba.lastname, so.customer_lastname, '') AS payment_lastname,
    COALESCE(ba.company, '') AS payment_company,
    COALESCE(ba.street, '') AS payment_address_1,
    '' AS payment_address_2,
    COALESCE(ba.city, '') AS payment_city,
    COALESCE(ba.postcode, '') AS payment_postcode,
    'Brasil' AS payment_country,
    30 AS payment_country_id,
    COALESCE(ba.region, '') AS payment_zone,
    COALESCE(bz.zone_id, 0) AS payment_zone_id,
    '' AS payment_address_format,
    '' AS payment_custom_field,
    COALESCE(sop.method, '') AS payment_method,
    COALESCE(sop.method, '') AS payment_code,
    COALESCE(sa.firstname, so.customer_firstname, '') AS shipping_firstname,
    COALESCE(sa.lastname, so.customer_lastname, '') AS shipping_lastname,
    COALESCE(sa.company, '') AS shipping_company,
    COALESCE(sa.street, '') AS shipping_address_1,
    '' AS shipping_address_2,
    COALESCE(sa.city, '') AS shipping_city,
    COALESCE(sa.postcode, '') AS shipping_postcode,
    'Brasil' AS shipping_country,
    30 AS shipping_country_id,
    COALESCE(sa.region, '') AS shipping_zone,
    COALESCE(sz.zone_id, 0) AS shipping_zone_id,
    '' AS shipping_address_format,
    '' AS shipping_custom_field,
    COALESCE(so.shipping_method, '') AS shipping_method,
    COALESCE(so.shipping_method, '') AS shipping_code,
    '' AS comment,
    CAST(so.grand_total AS DECIMAL(15,4)) AS total,
    -- Sempre status 1 (Pendente) pois SECTRA filtra: order_status_id = 1
    1 AS order_status_id,
    0 AS affiliate_id,
    CAST(0 AS DECIMAL(15,4)) AS commission,
    0 AS marketing_id,
    '' AS tracking,
    2 AS language_id,
    2 AS currency_id,
    'BRL' AS currency_code,
    CAST(1.00000000 AS DECIMAL(15,8)) AS currency_value,
    COALESCE(so.remote_ip, '') AS ip,
    '' AS forwarded_ip,
    '' AS user_agent,
    '' AS accept_language,
    so.created_at AS date_added,
    so.updated_at AS date_modified
FROM sales_order so
LEFT JOIN sales_order_address ba ON ba.parent_id = so.entity_id AND ba.address_type = 'billing'
LEFT JOIN sales_order_address sa ON sa.parent_id = so.entity_id AND sa.address_type = 'shipping'
LEFT JOIN sales_order_payment sop ON sop.parent_id = so.entity_id
LEFT JOIN oc_zone bz ON bz.name COLLATE utf8mb4_0900_ai_ci = ba.region AND bz.country_id = 30
LEFT JOIN oc_zone sz ON sz.name COLLATE utf8mb4_0900_ai_ci = sa.region AND sz.country_id = 30
LEFT JOIN oc_customer_id_map m ON m.magento_customer_id = so.customer_id
WHERE so.customer_id IS NOT NULL
  AND so.state IN ('new', 'pending_payment', 'processing')
  AND NOT EXISTS (SELECT 1 FROM oc_order_imported oi WHERE oi.order_id = so.entity_id + 200000);

-- ---------------------------------------------------------------
-- 16. VIEW: oc_order_product (itens do pedido)
--
--     Mapeamento:
--     - product_id: usa oc_product_id_map por SKU para resolver ID antigo do OpenCart
--       Se não encontrar, fallback para product_id + 200000
--     - model: SKU do Magento (SECTRA não usa este campo para lookup)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW oc_order_product AS
SELECT
    soi.item_id + 200000 AS order_product_id,
    soi.order_id + 200000 AS order_id,
    COALESCE(pm.old_oc_product_id, soi.product_id + 200000) AS product_id,
    COALESCE(soi.name, '') AS name,
    COALESCE(soi.sku, '') AS model,
    CAST(soi.qty_ordered AS SIGNED) AS quantity,
    CAST(soi.price AS DECIMAL(15,4)) AS price,
    CAST(soi.row_total AS DECIMAL(15,4)) AS total,
    CAST(COALESCE(soi.tax_amount, 0) AS DECIMAL(15,4)) AS tax,
    0 AS reward
FROM sales_order_item soi
INNER JOIN sales_order so ON so.entity_id = soi.order_id
LEFT JOIN oc_product_id_map pm ON pm.magento_sku = TRIM(soi.sku)
WHERE soi.parent_item_id IS NULL
  AND soi.qty_ordered > 0
  AND so.customer_id IS NOT NULL
  AND so.state IN ('new', 'pending_payment', 'processing');

-- ---------------------------------------------------------------
-- 17. VIEW: oc_order_total (totais: subtotal + frete + total)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW oc_order_total AS
SELECT
    (so.entity_id + 200000) * 10 + 1 AS order_total_id,
    so.entity_id + 200000 AS order_id,
    'sub_total' AS code,
    'Sub-Total' AS title,
    CAST(so.subtotal AS DECIMAL(15,4)) AS `value`,
    1 AS sort_order
FROM sales_order so
WHERE so.customer_id IS NOT NULL AND so.state IN ('new', 'pending_payment', 'processing')
UNION ALL
SELECT
    (so.entity_id + 200000) * 10 + 2,
    so.entity_id + 200000,
    'shipping',
    COALESCE(so.shipping_description, 'Frete'),
    CAST(so.shipping_amount AS DECIMAL(15,4)),
    3
FROM sales_order so
WHERE so.customer_id IS NOT NULL AND so.state IN ('new', 'pending_payment', 'processing')
AND so.shipping_amount > 0
UNION ALL
SELECT
    (so.entity_id + 200000) * 10 + 3,
    so.entity_id + 200000,
    'total',
    'Total',
    CAST(so.grand_total AS DECIMAL(15,4)),
    9
FROM sales_order so
WHERE so.customer_id IS NOT NULL AND so.state IN ('new', 'pending_payment', 'processing');

-- ---------------------------------------------------------------
-- 18. VIEW: oc_customer (clientes Magento → formato OpenCart)
--
--     - customer_id: +200000 offset
--     - customer_group_id: 2 (PJ) se tem CNPJ, 1 (PF) caso contrário
--     - custom_field: CNPJ sem formatação no key "6"
--     - telephone: sem formatação (apenas dígitos)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW oc_customer AS
SELECT
    ce.entity_id + 200000 AS customer_id,
    CASE WHEN ce.taxvat IS NOT NULL AND ce.taxvat <> '' THEN 2 ELSE 1 END AS customer_group_id,
    ce.store_id AS store_id,
    2 AS language_id,
    COALESCE(ce.firstname, '') AS firstname,
    COALESCE(ce.lastname, '') AS lastname,
    COALESCE(ce.email, '') AS email,
    COALESCE(REPLACE(REPLACE(REPLACE(REPLACE(ca.telephone, '(', ''), ')', ''), '-', ''), ' ', ''), '') AS telephone,
    '' AS fax,
    '' AS `password`,
    '' AS salt,
    NULL AS cart,
    NULL AS wishlist,
    0 AS newsletter,
    0 AS address_id,
    CONCAT(
        '{"6":"',
        REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(ce.taxvat, ''), '.', ''), '/', ''), '-', ''), ' ', ''),
        '","2":"","3":"","1":""}'
    ) AS custom_field,
    '' AS ip,
    1 AS status,
    1 AS safe,
    '' AS token,
    '' AS code,
    ce.created_at AS date_added
FROM customer_entity ce
LEFT JOIN customer_address_entity ca
    ON ca.parent_id = ce.entity_id
    AND ca.entity_id = (SELECT MIN(ca2.entity_id) FROM customer_address_entity ca2 WHERE ca2.parent_id = ce.entity_id);

-- ---------------------------------------------------------------
-- 19. VIEW: oc_address (endereços Magento → formato OpenCart)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW oc_address AS
SELECT
    ca.entity_id + 200000 AS address_id,
    ca.parent_id + 200000 AS customer_id,
    COALESCE(ca.firstname, '') AS firstname,
    COALESCE(ca.lastname, '') AS lastname,
    COALESCE(ca.company, CONCAT(COALESCE(ca.firstname, ''), ' ', COALESCE(ca.lastname, ''))) AS company,
    COALESCE(SUBSTRING_INDEX(ca.street, '\n', 1), '') AS address_1,
    COALESCE(
        CASE WHEN LOCATE('\n', ca.street) > 0
             THEN TRIM(REPLACE(SUBSTR(ca.street, LOCATE('\n', ca.street) + 1), '\n', ', '))
             ELSE ''
        END, ''
    ) AS address_2,
    COALESCE(ca.city, '') AS city,
    COALESCE(ca.postcode, '') AS postcode,
    30 AS country_id,
    COALESCE(zm.oc_zone_id, 0) AS zone_id,
    '[]' AS custom_field
FROM customer_address_entity ca
LEFT JOIN oc_zone_mapping zm ON zm.magento_region_id = ca.region_id;

-- ---------------------------------------------------------------
-- 20. Verificação de contagem
-- ---------------------------------------------------------------
SELECT 'oc_order' AS view_name, COUNT(*) AS records FROM oc_order
UNION ALL SELECT 'oc_order_product', COUNT(*) FROM oc_order_product
UNION ALL SELECT 'oc_order_total',   COUNT(*) FROM oc_order_total
UNION ALL SELECT 'oc_order_status',  COUNT(*) FROM oc_order_status
UNION ALL SELECT 'oc_customer',      COUNT(*) FROM oc_customer
UNION ALL SELECT 'oc_address',       COUNT(*) FROM oc_address
UNION ALL SELECT 'oc_setting',       COUNT(*) FROM oc_setting
UNION ALL SELECT 'oc_language',      COUNT(*) FROM oc_language
UNION ALL SELECT 'oc_currency',      COUNT(*) FROM oc_currency
UNION ALL SELECT 'oc_country',       COUNT(*) FROM oc_country
UNION ALL SELECT 'oc_customer_group', COUNT(*) FROM oc_customer_group
UNION ALL SELECT 'oc_zone',          COUNT(*) FROM oc_zone
UNION ALL SELECT 'oc_zone_mapping',  COUNT(*) FROM oc_zone_mapping
UNION ALL SELECT 'oc_custom_field',  COUNT(*) FROM oc_custom_field
UNION ALL SELECT 'oc_customer_id_map', COUNT(*) FROM oc_customer_id_map
UNION ALL SELECT 'oc_product_id_map', COUNT(*) FROM oc_product_id_map
UNION ALL SELECT 'oc_order_imported', COUNT(*) FROM oc_order_imported
UNION ALL SELECT 'oc_pre_registration', COUNT(*) FROM oc_pre_registration;
