<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Exceptions/PaymentDeclinedException.php
 * CAPA: Capa de Negocio (Clase 11) — Excepción de dominio financiero
 * ==============================================================================
 *
 * TEORÍA APLICADA — Excepciones tipadas vs genéricas:
 *
 *  1. SEMÁNTICA DE NEGOCIO: un rechazo de tarjeta NO es un error 500 del
 *     servidor, es una REGLA DE NEGOCIO ("el cobro no procedió"). Al tiparla,
 *     el controlador la captura por separado y devuelve al checkout con un
 *     mensaje en español, SIN crear la orden y SIN despachar el Job.
 *
 *  2. FÁBRICAS ESTÁTICAS (named constructors): cada motivo de rechazo tiene
 *     su constructor (fondosInsuficientes, tarjetaVencida...). Clean Code:
 *     el código cliente lee la intención sin adivinar strings.
 *
 *  3. SIN DATOS SENSIBLES: el mensaje nunca incluye el PAN ni el CVV, solo el
 *     motivo (trazabilidad segura para auditoría y BI).
 */

namespace App\Exceptions;

use Exception;

class PaymentDeclinedException extends Exception
{
    /** Motivo machine-readable para la auditoría/BI (fondos, vencida, ...). */
    public function __construct(
        string $message = 'El pago con tarjeta fue rechazado.',
        public readonly string $motivo = 'rechazo_generico',
        public readonly array $contexto = []
    ) {
        parent::__construct($message);
    }

    /** La tarjeta no pasa el checksum Luhn (tipeo erróneo o número ficticio). */
    public static function tarjetaInvalida(): self
    {
        return new self(
            'El número de tarjeta no es válido. Verifícalo e inténtalo de nuevo.',
            'numero_invalido'
        );
    }

    /** El plástico venció (dominio temporal). */
    public static function tarjetaVencida(): self
    {
        return new self(
            'Tu tarjeta está vencida. Usa otra tarjeta vigente.',
            'tarjeta_vencida'
        );
    }

    /** CVV con formato incorrecto (posesión del plástico no verificable). */
    public static function cvvInvalido(): self
    {
        return new self(
            'El código de seguridad (CVV) no es válido.',
            'cvv_invalido'
        );
    }

    /** La pasarela aprobó la tarjeta pero rechazó el cargo por saldo. */
    public static function fondosInsuficientes(float $monto, string $moneda): self
    {
        return new self(
            'Fondos insuficientes para completar el cobro.',
            'fondos_insuficientes',
            ['monto' => $monto, 'moneda' => $moneda]
        );
    }
}
