<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Services/PaymentService.php
 * CAPA: Capa de Negocio (Clase 11) — Servicio de cobro con tarjeta
 * ==============================================================================
 *
 * TEORÍA APLICADA — Servicio de dominio + Anti-Corruption Layer financiero:
 *
 *  1. AISLAMIENTO DE LA REGLA DE NEGOCIO: el controlador NO sabe cómo se cobra;
 *     solo invoca cobrar(). Si mañana se integra Stripe/Openpay real, este es
 *     el ÚNICO archivo que cambia (principio Abierto/Cerrado + DIP).
 *
 *  2. VALIDACIÓN LUHN: el número de tarjeta se verifica con el algoritmo de
 *     Luhn (checksum módulo 10, estándar ISO/IEC 7812). Detecta errores de
 *     tipeo ANTES de intentar el cargo (fail-fast).
 *
 *  3. DETECCIÓN DE MARCA por prefijos IIN/BIN: 4 → Visa, 51-55/2221-2720 →
 *     Mastercard, 34/37 → Amex. La marca se persiste (dato no sensible) y se
 *     muestra al usuario ("Visa **** 4242").
 *
 *  4. PASARELA SIMULADA: emula latencia y reglas de aprobación/rechazo sin
 *     depender de un proveedor externo (idéntico patrón al simulador de
 *     YAuctions). Reglas demo: la tarjeta 4000...0002 simula fondos
 *     insuficientes; cualquier otra válida aprueba. En producción este método
 *     haría Http::post() a la pasarela real con idempotency-key = orden.
 *
 *  5. SEGURIDAD: el PAN y el CVV NUNCA se persisten ni se registran en logs;
 *     solo viajan en memoria dentro de este método y se descartan al retornar.
 */

namespace App\Services;

use App\Exceptions\PaymentDeclinedException;

class PaymentService
{
    /**
     * Cobra un monto con tarjeta. Retorna los datos NO sensibles del cargo.
     *
     * @param  array{numero:string, titular:string, vencimiento:string, cvv:string} $tarjeta
     * @return array{marca:string, ultimos4:string, referencia:string}
     *
     * @throws PaymentDeclinedException si la tarjeta no pasa Luhn o la
     *         pasarela rechaza el cargo (fondos, vencimiento, CVV, etc.).
     */
    public function cobrar(array $tarjeta, float $monto, string $moneda, int $ordenId): array
    {
        $numero = preg_replace('/\D/', '', $tarjeta['numero']);

        // REGLA 1: checksum Luhn (fail-fast, sin costo de red).
        if (! $this->pasaLuhn($numero)) {
            throw PaymentDeclinedException::tarjetaInvalida();
        }

        // REGLA 2: vencimiento MM/AA debe ser futuro (dominio temporal).
        if (! $this->vencimientoVigente($tarjeta['vencimiento'])) {
            throw PaymentDeclinedException::tarjetaVencida();
        }

        // REGLA 3: CVV de 3-4 dígitos (verificación de posesión del plástico).
        if (! preg_match('/^\d{3,4}$/', $tarjeta['cvv'])) {
            throw PaymentDeclinedException::cvvInvalido();
        }

        // PASARELA SIMULADA: latencia de red emulada (como un proveedor real).
        usleep(300000); // 300 ms

        // Regla demo documentada: esta tarjeta de prueba simula sin fondos.
        if ($numero === '4000000000000002') {
            throw PaymentDeclinedException::fondosInsuficientes($monto, $moneda);
        }

        $marca = $this->detectarMarca($numero);

        return [
            'marca'     => $marca,
            'ultimos4'  => substr($numero, -4),
            // Referencia única del cargo (idempotente por orden en producción).
            'referencia' => 'PAY-' . $ordenId . '-' . strtoupper(substr(md5($numero . $ordenId . microtime()), 0, 8)),
        ];
    }

    /**
     * Algoritmo de Luhn (ISO/IEC 7812): suma alternada con doblado; el total
     * debe ser múltiplo de 10. Detecta el ~100% de errores de un dígito.
     */
    public function pasaLuhn(string $numero): bool
    {
        if (strlen($numero) < 13 || strlen($numero) > 19 || ! ctype_digit($numero)) {
            return false;
        }

        $suma = 0;
        $doblar = false;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = (int) $numero[$i];

            if ($doblar) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }

            $suma += $digito;
            $doblar = ! $doblar;
        }

        return $suma % 10 === 0;
    }

    /**
     * Detección de marca por rangos IIN/BIN (primeros dígitos del PAN).
     */
    public function detectarMarca(string $numero): string
    {
        if (str_starts_with($numero, '4')) {
            return 'visa';
        }

        $prefijo2 = (int) substr($numero, 0, 2);
        $prefijo4 = (int) substr($numero, 0, 4);

        if (($prefijo2 >= 51 && $prefijo2 <= 55) || ($prefijo4 >= 2221 && $prefijo4 <= 2720)) {
            return 'mastercard';
        }

        if (str_starts_with($numero, '34') || str_starts_with($numero, '37')) {
            return 'amex';
        }

        return 'desconocida';
    }

    /**
     * Vencimiento "MM/AA": válido si el último día de ese mes es futuro.
     */
    private function vencimientoVigente(string $vencimiento): bool
    {
        if (! preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $vencimiento, $m)) {
            return false;
        }

        $finDeMes = \DateTime::createFromFormat('y-m-d H:i:s', $m[2] . '-' . $m[1] . '-01 00:00:00');
        if (! $finDeMes) {
            return false;
        }
        $finDeMes->modify('last day of this month')->setTime(23, 59, 59);

        return $finDeMes > new \DateTime();
    }
}
