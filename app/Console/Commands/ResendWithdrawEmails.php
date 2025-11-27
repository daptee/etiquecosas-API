<?php

namespace App\Console\Commands;

use App\Mail\OrderWithdrawMail;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResendWithdrawEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:resend-withdraw-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reenviar correos de retiro por local a pedidos específicos que recibieron el correo incorrecto';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // IDs de las ventas que necesitan el correo correcto
        $saleIds = [
            94521, 94813, 94766, 94658, 94655, 94650, 94643, 94626,
            94610, 94609, 94604, 94563, 94561, 94558, 94555, 94535,
            94530, 94518
        ];

        $this->info('Iniciando reenvío de correos de retiro por local...');
        $this->info('Total de pedidos: ' . count($saleIds));
        $this->newLine();

        $sales = Sale::with(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality'])
            ->whereIn('id', $saleIds)
            ->get();

        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        $progressBar = $this->output->createProgressBar(count($sales));
        $progressBar->start();

        foreach ($sales as $sale) {
            try {
                // Verificar que el cliente existe
                if (!$sale->client) {
                    $this->newLine();
                    $this->error("❌ Venta #{$sale->id} NO tiene cliente asociado");

                    Log::error("Venta sin cliente", [
                        'sale_id' => $sale->id
                    ]);

                    $failureCount++;
                    $progressBar->advance();
                    continue;
                }

                // Verificar que el pedido tenga retiro por local (shipping_method_id == 1)
                if ($sale->shipping_method_id == 1) {
                    Mail::to($sale->client->email)->send(new OrderWithdrawMail($sale));

                    $this->newLine();
                    $this->info("✅ Correo enviado a venta #{$sale->id} - {$sale->client->email}");

                    Log::info("Correo de retiro reenviado correctamente", [
                        'sale_id' => $sale->id,
                        'client_email' => $sale->client->email
                    ]);

                    $successCount++;
                } else {
                    $this->newLine();
                    $this->warn("⚠️  Venta #{$sale->id} NO tiene retiro por local (shipping_method_id: {$sale->shipping_method_id})");

                    Log::warning("Venta saltada por no tener retiro por local", [
                        'sale_id' => $sale->id,
                        'shipping_method_id' => $sale->shipping_method_id
                    ]);

                    $skippedCount++;
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("❌ Error enviando correo para venta #{$sale->id}: " . $e->getMessage());

                Log::error("Error enviando correo de retiro", [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage()
                ]);

                $failureCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Resumen final
        $this->info('═══════════════════════════════════════');
        $this->info('         RESUMEN DEL PROCESO          ');
        $this->info('═══════════════════════════════════════');
        $this->info("✅ Correos enviados exitosamente: {$successCount}");

        if ($skippedCount > 0) {
            $this->warn("⚠️  Pedidos saltados (no retiro local): {$skippedCount}");
        }

        if ($failureCount > 0) {
            $this->error("❌ Errores: {$failureCount}");
        }

        $this->info('═══════════════════════════════════════');
        $this->newLine();

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
