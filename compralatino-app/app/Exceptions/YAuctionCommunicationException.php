<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Exceptions/YAuctionCommunicationException.php
 * CAPA: Capa de Negocio — Modelo de Excepciones (Clase 11)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Excepciones de dominio:
 *
 * En Clean Code y en el patrón Application Layer, los ERRORES DE NEGOCIO se
 * modelan como EXCEPCIONES TIPOADAS (no como códigos de retorno sueltos ni
 * como booleanos). Esto aporta:
 *
 *  • Semántica: "falló la comunicación con YAuctions" es un CONCEPTO del
 *    dominio, distinto de un error genérico de red o de un error 500.
 *  • Flujo de control limpio: el Job puede capturar específicamente ESTA
 *    excepción en su bloque try/catch sin capturar errores ajenos (fail-safe).
 *  · Trazabilidad: transporta el CONTEXTO del fallo (payload enviado, estado
 *    HTTP, cuerpo de respuesta) que luego se persiste como auditoría.
 */

namespace App\Exceptions;

use Exception;
use Throwable;

class YAuctionCommunicationException extends Exception
{
    /**
     * Contexto completo del fallo: qué se envió, qué se recibió, error base.
     * Se registrará en orders.datos_sincronizacion (bitácora de auditoría).
     */
    protected array $contexto = [];

    /**
     * @param string           $mensaje  Descripción legible del fallo de negocio.
     * @param array            $contexto Payload/respuesta involucrados.
     * @param int              $codigo   Estado HTTP o código interno.
     * @param Throwable|null   $anterior Excepción técnica original (encadenamiento).
     */
    public function __construct(
        string $mensaje = 'No fue posible comunicarse con la tienda externa YAuctions.',
        array $contexto = [],
        int $codigo = 0,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->contexto = $contexto;
    }

    /** Accesor de la data de auditoría que usará el Job para persistirla. */
    public function contexto(): array
    {
        return $this->contexto;
    }

    /**
     * Fábrica estática: mejora la legibilidad en el punto de lanzamiento
     * (self-documenting code):
     *   throw YAuctionCommunicationException::porTimeout($payload, $e);
     */
    public static function porTimeout(array $payload, ?Throwable $anterior = null): self
    {
        return new self(
            'Tienda YAuctions no responde dentro del tiempo límite.',
            ['tipo' => 'timeout', 'payload' => $payload],
            408,
            $anterior
        );
    }

    public static function porErrorHttp(int $statusCode, ?array $respuesta, array $payload): self
    {
        return new self(
            "YAuctions respondió con el estado HTTP {$statusCode}.",
            ['tipo' => 'http_error', 'status' => $statusCode, 'respuesta' => $respuesta ?? '(cuerpo no JSON)', 'payload' => $payload],
            $statusCode
        );
    }

    public static function porReglaDeNegocio(string $motivo, array $payload = []): self
    {
        return new self(
            "Regla de negocio violada: {$motivo}",
            ['tipo' => 'regla_negocio', 'motivo' => $motivo, 'payload' => $payload],
            422
        );
    }
}
