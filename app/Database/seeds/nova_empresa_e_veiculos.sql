-- =====================================================
-- Dados de Teste - API de Consulta de Multas
-- Tenant: 1111111111111
-- CNPJ: 33.683.111/0001-07
-- =====================================================

-- Empresa (matrizes_filiais)
INSERT INTO matrizes_filiais (chave, tipo, razao_social, nome_fantasia, cpf_cnpj)
VALUES ('1111111111111', 'M', 'Empresa Teste Multas', 'Teste Multas API', '33.683.111/0001-07');

SET @id_empresa = LAST_INSERT_ID();

-- Veiculos (10 placas: SAV0741 a SAV0750)
INSERT INTO veiculos (chave, id_matriz_filial, diagrama, placa, marca, modelo, ano, cor, id_grupo, disponibilidade) VALUES
('1111111111111', @id_empresa, 'Hatch.jpg',             'SAV0741', 'Fiat',      'Mobi',     '2024/2025', 'Prata', 1,    'D'),
('1111111111111', @id_empresa, 'Sedan.jpg',             'SAV0742', 'VW',        'Virtus',   '2024/2025', 'Prata', 2,    'D'),
('1111111111111', @id_empresa, 'SUV.jpg',               'SAV0743', 'Hyundai',   'Creta',    '2024/2025', 'Prata', 18,   'D'),
('1111111111111', @id_empresa, 'Hatch4drs.jpg',         'SAV0744', 'Chevrolet', 'Onix',     '2024/2025', 'Prata', 2287, 'D'),
('1111111111111', @id_empresa, 'Sedan.jpg',             'SAV0745', 'Toyota',    'Corolla',  '2024/2025', 'Prata', 1,    'D'),
('1111111111111', @id_empresa, 'SUV.jpg',               'SAV0746', 'Jeep',      'Renegade', '2024/2025', 'Prata', 2,    'D'),
('1111111111111', @id_empresa, 'Hatch.jpg',             'SAV0747', 'Renault',   'Kwid',     '2024/2025', 'Prata', 18,   'D'),
('1111111111111', @id_empresa, 'PickupCabineDupla.jpg', 'SAV0748', 'Fiat',      'Toro',     '2024/2025', 'Prata', 2287, 'D'),
('1111111111111', @id_empresa, 'Hatch4drs.jpg',         'SAV0749', 'Honda',     'Fit',      '2024/2025', 'Prata', 1,    'D'),
('1111111111111', @id_empresa, 'Sedan.jpg',             'SAV0750', 'Nissan',    'Versa',    '2024/2025', 'Prata', 2,    'D');
