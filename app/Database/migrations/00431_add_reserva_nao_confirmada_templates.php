<?php
use App\Database\Migration;

/** Templates globais; preserva personalizacoes de cada tenant. */
return new class extends Migration
{
    private const SLUG = 'reserva_nao_confirmada';

    public function up(): void
    {
        $type = $this->db()->table('message_template_types')->withoutChave()->where('slug', '=', self::SLUG)->first();
        $id = $type ? (int) $type['id'] : $this->db()->table('message_template_types')->withoutChave()->insert([
            'slug' => self::SLUG,
            'name_key' => 'templates.types.reserva_nao_confirmada',
            'description_key' => 'templates.types.reserva_nao_confirmada_desc',
            'category' => 'rental',
            'channels' => '["email","whatsapp","sms"]',
            'available_variables' => '["cliente","empresa","locacao"]',
            'sort_order' => 11, 'is_active' => 1,
        ]);
        foreach ($this->textos() as $locale => $texto) {
            $plain = implode("\n\n", $texto['paragraphs']);
            foreach (['email', 'whatsapp', 'sms'] as $channel) {
                // A chave global '0' e falsy no QueryBuilder; explicitar seu filtro.
                $exists = $this->db()->table('message_templates')->withChave('0')->where('chave', '=', '0')
                    ->where('template_type_id', '=', $id)->where('locale', '=', $locale)
                    ->where('channel', '=', $channel)->exists();
                if ($exists) continue;
                $html = '<h2>' . $texto['name'] . '</h2><p>' . implode('</p><p>', $texto['paragraphs']) . '</p>';
                $this->db()->table('message_templates')->withChave('0')->insert([
                    'chave' => '0', 'template_type_id' => $id, 'locale' => $locale,
                    'channel' => $channel, 'is_active' => 1,
                    'subject' => $channel === 'email' ? $texto['subject'] : null,
                    'content' => match ($channel) {
                        'email' => $html,
                        'whatsapp' => '*' . $texto['name'] . "*\n\n" . $plain,
                        'sms' => $texto['sms'],
                    },
                    'content_plain' => $channel === 'email' ? $plain : null,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Nao apagar personalizacoes criadas depois da publicacao.
        throw new \RuntimeException('Migration aditiva: preserve os templates e desative o fluxo para reverter.');
    }

    private function textos(): array
    {
        return [
            'pt_BR' => [
                'name' => 'Reserva não confirmada',
                'subject' => 'Sobre seu pedido de reserva nº {{locacao.numero}}',
                'sms' => 'Sentimos muito: reserva {{locacao.numero}} não confirmada. Fale com nossa equipe para consultar alternativas.',
                'paragraphs' => [
                    'Olá, {{cliente.primeiro_nome}}!',
                    'Agradecemos por escolher a {{empresa.nome_fantasia}}. Infelizmente, não foi possível confirmar seu pedido de reserva nº {{locacao.numero}}.',
                    'Pedimos desculpas pelo inconveniente. Se desejar, entre em contato com nossa equipe para verificarmos outras datas ou alternativas que possam atender às suas necessidades.',
                    'Agradecemos pela compreensão e esperamos ter a oportunidade de atender você em breve.',
                ],
            ],
            'pt_PT' => [
                'name' => 'Reserva não confirmada',
                'subject' => 'Sobre o seu pedido de reserva n.º {{locacao.numero}}',
                'sms' => 'Lamentamos: reserva {{locacao.numero}} não confirmada. Contacte a nossa equipa para consultar alternativas.',
                'paragraphs' => [
                    'Olá, {{cliente.primeiro_nome}}!',
                    'Agradecemos por escolher a {{empresa.nome_fantasia}}. Infelizmente, não foi possível confirmar o seu pedido de reserva n.º {{locacao.numero}}.',
                    'Pedimos desculpa pelo incómodo. Se desejar, contacte a nossa equipa para verificarmos outras datas ou alternativas que possam responder às suas necessidades.',
                    'Agradecemos a sua compreensão e esperamos ter a oportunidade de lhe prestar os nossos serviços em breve.',
                ],
            ],
            'en_US' => [
                'name' => 'Reservation not confirmed',
                'subject' => 'About your reservation request #{{locacao.numero}}',
                'sms' => 'We are sorry: reservation {{locacao.numero}} could not be confirmed. Please contact our team for alternatives.',
                'paragraphs' => [
                    'Hello, {{cliente.primeiro_nome}}!',
                    'Thank you for choosing {{empresa.nome_fantasia}}. Unfortunately, we were unable to confirm your reservation request #{{locacao.numero}}.',
                    'We apologize for the inconvenience. Please contact our team if you would like us to check other dates or alternatives that may suit your needs.',
                    'Thank you for your understanding. We hope to have the opportunity to welcome you soon.',
                ],
            ],
            'es_ES' => [
                'name' => 'Reserva no confirmada',
                'subject' => 'Sobre su solicitud de reserva n.º {{locacao.numero}}',
                'sms' => 'Lo sentimos: no se pudo confirmar la reserva {{locacao.numero}}. Contacte con nuestro equipo para consultar alternativas.',
                'paragraphs' => [
                    '¡Hola, {{cliente.primeiro_nome}}!',
                    'Gracias por elegir {{empresa.nome_fantasia}}. Lamentablemente, no ha sido posible confirmar su solicitud de reserva n.º {{locacao.numero}}.',
                    'Le pedimos disculpas por las molestias. Si lo desea, póngase en contacto con nuestro equipo para consultar otras fechas o alternativas que puedan adaptarse a sus necesidades.',
                    'Gracias por su comprensión. Esperamos tener la oportunidad de atenderle pronto.',
                ],
            ],
            'it_IT' => [
                'name' => 'Prenotazione non confermata',
                'subject' => 'La sua richiesta di prenotazione n. {{locacao.numero}}',
                'sms' => 'Ci dispiace: prenotazione {{locacao.numero}} non confermata. Contatti il nostro team per valutare alternative.',
                'paragraphs' => [
                    'Buongiorno, {{cliente.primeiro_nome}}!',
                    'Grazie per aver scelto {{empresa.nome_fantasia}}. Purtroppo non è stato possibile confermare la sua richiesta di prenotazione n. {{locacao.numero}}.',
                    'Ci scusiamo per il disagio. Se lo desidera, contatti il nostro team per verificare altre date o alternative che possano soddisfare le sue esigenze.',
                    'La ringraziamo per la comprensione e speriamo di avere presto l’opportunità di accoglierla.',
                ],
            ],
        ];
    }
};
