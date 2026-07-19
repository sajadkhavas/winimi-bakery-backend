<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        $requiresAddress = $this->input('deliveryMethod') !== DeliveryMethod::Pickup->value;

        return [
            'customer' => ['required', 'array'],
            'customer.fullName' => ['required', 'string', 'min:2', 'max:120'],
            'customer.mobile' => ['required', 'string', 'max:32'],
            'customer.province' => [Rule::requiredIf($requiresAddress), 'nullable', 'string', 'max:100'],
            'customer.city' => [Rule::requiredIf($requiresAddress), 'nullable', 'string', 'max:100'],
            'customer.address' => [Rule::requiredIf($requiresAddress), 'nullable', 'string', 'max:1200'],
            'customer.postalCode' => ['nullable', 'string', 'max:20'],
            'customer.notes' => ['nullable', 'string', 'max:1000'],
            'deliveryMethod' => ['required', Rule::enum(DeliveryMethod::class)],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*' => ['required', 'array'],
            'items.*.variantId' => ['required', 'string', 'size:26'],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.max(1, (int) config('winimi.checkout.max_quantity_per_line', 20)),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $total = collect($this->input('items', []))->sum(
                    fn (array $item): int => (int) ($item['quantity'] ?? 0),
                );
                $maximum = max(1, (int) config('winimi.checkout.max_total_units', 50));

                if ($total > $maximum) {
                    $validator->errors()->add(
                        'items',
                        "تعداد کل اقلام هر سفارش نمی‌تواند بیشتر از {$maximum} باشد.",
                    );
                }
            },
        ];
    }
}
