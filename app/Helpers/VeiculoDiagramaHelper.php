<?php

namespace App\Helpers;

/** Catálogo dos assets estáticos usados pelo cadastro e checklist impresso. */
final class VeiculoDiagramaHelper
{
    public const PADRAO = 'sedan.jpg';

    public const ARQUIVOS = [
        'atv.jpg' => 'atv',
        'caravana.jpg' => 'caravana',
        'cargo.jpg' => 'cargo',
        'convercivel.jpg' => 'convercivel',
        'coupe.jpg' => 'coupe',
        'crossover.jpg' => 'crossover',
        'golfcart.jpg' => 'golfcart',
        'hatch.jpg' => 'hatch',
        'hatch4drs.jpg' => 'hatch4drs',
        'hibrido.jpg' => 'hibrido',
        'jeep.jpg' => 'jeep',
        'microvan.jpg' => 'microvan',
        'minicargo.jpg' => 'minicargo',
        'minisuv.jpg' => 'minisuv',
        'minivan.jpg' => 'minivan',
        'moto.jpg' => 'moto',
        'motocicleta.jpg' => 'motocicleta',
        'pickupCabineDupla.jpg' => 'pickupcabinedupla',
        'pickupCabineSimples.jpg' => 'pickupcabinesimples',
        'scooter.jpg' => 'scooter',
        'sedan.jpg' => 'sedan',
        'shuttle.jpg' => 'shuttle',
        'sport.jpg' => 'sport',
        'suv.jpg' => 'suv',
        'truck.jpg' => 'truck',
        'van.jpg' => 'van',
        'wagon.jpg' => 'wagon',
    ];

    public static function normalizar(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }
        foreach (self::ARQUIVOS as $arquivo => $rotulo) {
            if (strcasecmp($arquivo, $valor) === 0) {
                return $arquivo;
            }
        }
        return null;
    }
}
