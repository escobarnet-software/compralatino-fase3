<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Requests/StoreOrderRequest.php
 * CAPA: Capa de Presentación — Validación de Entrada (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Form Request (patrón Facade sobre el Validador):
 *
 * El Form Request es una SUBCLASE del Validador de Laravel que aplica el
 * principio de DEFENSA EN PROFUNDIDAD: antes de que cualquier dato llegue a la
 * Capa de Negocio, la Capa de Presentación verifica su FORMA y su DOMINIO:
 *
 *  1. AUTORIZACIÓN (authorize): política de acceso. Si el usuario no está
 *     autenticado o intenta modificar la orden de otro, la petición se aborta
 *     con 403 ANTES de tocar la lógica de negocio (prevención de IDOR/BOLA).
 *
 *  2. REGLAS DE VALIDACIÓN (rules): cada campo tiene reglas declarativas:
 *     • 'exists:products,id'  → INTEGRIDAD REFERENCIAL a nivel de aplicación
 *       (no basta con la FK de la BD; se evita el round-trip de error SQL).
 *     • 'integer|min:1'       → INTEGRIDAD DE DOMINIO de la cantidad.
 *     • 'in:local,yauctions'  → DOMINIO CERRADO multiplataforma (Fase III).
 *
 *  3. DRY (Don't Repeat Yourself): $request->validated() entrega SOLO los
 *     datos saneados y confirmados. El controlador nunca accede a $request->all()
 *     (que incluye basura o intentos de inyección de campos extra / mass-assignment).
 *
 *  4. UX: mensajes() y attributes() personalizan errores en español, mejorando
 *     la retroalimentación al usuario final sin contaminar la capa de negocio.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * ¿PERMITE la solicitud este endpoint?
     * Se exige autenticación; el rol se maneja en las rutas (middleware).
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * REGLAS DECLARATIVAS — la validación es la PRIMERA línea de defensa.
     */
    public function rules(): array
    {
        return [
            // El producto debe existir en el catálogo (integridad referencial temprana).
            'product_id' => ['required', 'integer', 'exists:products,id'],

            // Cantidad: entero positivo con tope razonable (anti-automatización).
            'cantidad'   => ['required', 'integer', 'min:1', 'max:10'],

            // Multiplataforma: dominio cerrado; impide valores arbitrarios.
            'plataforma' => ['required', Rule::in(['local', 'yauctions'])],
        ];
    }

    /**
     * Mensajes de error personalizados (capacidad de personalización del Form
     * Request): separan el idioma/presentación del motor de validación.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Debe seleccionar un producto para comprar.',
            'product_id.exists'   => 'El producto seleccionado ya no está disponible.',
            'cantidad.min'        => 'La cantidad mínima permitida es 1 unidad.',
            'cantidad.max'        => 'No se permiten compras mayores a 10 unidades por transacción.',
            'plataforma.in'       => 'La plataforma seleccionada no es válida.',
        ];
    }

    /**
     * Nombres amigables de los atributos (mejora la accesibilidad del error).
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'cantidad'   => 'cantidad',
            'plataforma' => 'plataforma de compra',
        ];
    }
}
