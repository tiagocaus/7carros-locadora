<?php

use App\Database\Migration;

/**
 * Suporta tomador estrangeiro sem tratar passaporte como CPF/CNPJ ou NIF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('clientes') && $this->columnExists('clientes', 'cpf_cnpj')) {
            $this->modifyColumn('clientes', 'cpf_cnpj', 'VARCHAR(40)', ['null' => true]);
        }

        if ($this->tableExists('nfse')) {
            if ($this->columnExists('nfse', 'tomador_cpf_cnpj')) {
                $this->modifyColumn('nfse', 'tomador_cpf_cnpj', 'VARCHAR(40)', ['null' => true]);
            }
            $this->addColumnIfNotExists('nfse', 'tomador_tipo', 'VARCHAR(2)', [
                'null' => true,
                'after' => 'tomador_cpf_cnpj',
                'comment' => 'Tipo do tomador no momento da emissao (PF/PJ/ES)',
            ]);
            $this->addColumnIfNotExists('nfse', 'tomador_pais', 'VARCHAR(2)', [
                'null' => true,
                'after' => 'tomador_tipo',
                'comment' => 'Pais ISO 3166-1 alpha-2 do tomador',
            ]);
        }

        if ($this->tableExists('paises')) {
            $this->addColumnIfNotExists('paises', 'codigo_bacen', 'VARCHAR(4)', [
                'null' => true,
                'after' => 'codigo',
                'comment' => 'Codigo de pais BACEN usado em documentos fiscais brasileiros',
            ]);

            $stmt = $this->pdo->prepare('UPDATE paises SET codigo_bacen = ? WHERE codigo = ?');
            foreach ($this->codigosBacen() as $iso => $bacen) {
                $stmt->execute([$bacen, $iso]);
            }
        }
    }

    public function down(): void
    {
        if ($this->tableExists('paises')) {
            $this->dropColumnIfExists('paises', 'codigo_bacen');
        }
        if ($this->tableExists('nfse')) {
            $this->dropColumnIfExists('nfse', 'tomador_pais');
            $this->dropColumnIfExists('nfse', 'tomador_tipo');
        }
        // VARCHAR(40) nao e reduzido: o rollback nao pode truncar passaportes ja gravados.
    }

    private function codigosBacen(): array
    {
        return [
            'AD'=>'0370','AE'=>'2445','AL'=>'0175','AM'=>'0647','AO'=>'0400','AR'=>'0639','AT'=>'0728','AU'=>'0698','AZ'=>'0736',
            'BA'=>'0981','BB'=>'0833','BD'=>'0817','BE'=>'0876','BG'=>'1112','BH'=>'0809','BO'=>'0973','BR'=>'1058','BS'=>'0779','BY'=>'0850','BZ'=>'0884',
            'CA'=>'1490','CD'=>'8885','CH'=>'7676','CI'=>'1937','CL'=>'1589','CM'=>'1457','CN'=>'1600','CO'=>'1694','CR'=>'1961','CU'=>'1996','CV'=>'1279','CY'=>'1635','CZ'=>'7919',
            'DE'=>'0230','DK'=>'2321','DO'=>'6475','DZ'=>'0590','EC'=>'2399','EE'=>'2518','EG'=>'2402','ES'=>'2453','ET'=>'2534','FI'=>'2712','FJ'=>'8702','FR'=>'2755',
            'GB'=>'6289','GE'=>'2917','GF'=>'3255','GH'=>'2895','GR'=>'3018','GT'=>'3174','GW'=>'3344','GY'=>'3379','HK'=>'3514','HN'=>'3450','HR'=>'1953','HT'=>'3417','HU'=>'3557',
            'ID'=>'3654','IE'=>'3751','IL'=>'3832','IN'=>'3611','IQ'=>'3697','IR'=>'3727','IS'=>'3794','IT'=>'3867','JM'=>'3913','JO'=>'4030','JP'=>'3999',
            'KE'=>'6238','KH'=>'1414','KR'=>'1902','KW'=>'1988','KZ'=>'1538','LB'=>'4316','LI'=>'4405','LK'=>'7501','LT'=>'4421','LU'=>'4456','LV'=>'4278',
            'MA'=>'4740','MC'=>'4952','MD'=>'4944','ME'=>'4985','MK'=>'4499','MM'=>'0930','MT'=>'4677','MX'=>'4936','MY'=>'4553','MZ'=>'5053',
            'NG'=>'5282','NI'=>'5215','NL'=>'5738','NO'=>'5380','NP'=>'5177','NZ'=>'5487','OM'=>'5568','PA'=>'5800','PE'=>'5894','PH'=>'2674','PK'=>'5762','PL'=>'6033','PT'=>'6076','PY'=>'5860',
            'QA'=>'1546','RO'=>'6700','RS'=>'7370','RU'=>'6769','RW'=>'6750','SA'=>'0531','SE'=>'7641','SG'=>'7412','SI'=>'2461','SK'=>'2470','SM'=>'6971','SN'=>'7285','SR'=>'7706','ST'=>'7200','SV'=>'6874','SY'=>'7447',
            'TH'=>'7765','TL'=>'7951','TN'=>'8206','TR'=>'8273','TT'=>'8150','TW'=>'1619','TZ'=>'7803','UA'=>'8311','UG'=>'8338','US'=>'2496','UY'=>'8451','UZ'=>'8478',
            'VA'=>'8486','VE'=>'8508','VN'=>'8583','YE'=>'3573','ZA'=>'7560',
        ];
    }
};
