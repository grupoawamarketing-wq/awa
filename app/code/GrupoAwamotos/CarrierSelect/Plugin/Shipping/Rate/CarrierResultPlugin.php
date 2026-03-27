<?php
declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Plugin\Shipping\Rate;

use Magento\Quote\Model\Quote\Address\RateResult\AbstractResult;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Shipping\Model\Rate\CarrierResult;

class CarrierResultPlugin
{
    /**
     * Remove rows de erro quando a cotacao tambem possui metodos validos.
     *
     * Isso evita que carriers fallback como tablerate exibam mensagens de
     * indisponibilidade no checkout ao mesmo tempo em que CarrierSelect
     * oferece um frete utilizavel.
     *
     * @param CarrierResult $subject
     * Alem de filtrar o retorno, o plugin normaliza o estado interno do
     * CarrierResult para que chamadas subsequentes a getError(),
     * getRatesByCarrier() e similares nao continuem enxergando rates de erro
     * que ja foram descartados.
     *
     * @param array<int, AbstractResult> $rates
     * @return array<int, AbstractResult>
     */
    public function afterGetAllRates(CarrierResult $subject, array $rates): array
    {
        $validRates = [];
        $hasErrorRates = false;

        foreach ($rates as $rate) {
            if ($rate instanceof Error) {
                $hasErrorRates = true;
                continue;
            }

            $validRates[] = $rate;
        }

        if ($validRates !== [] && $hasErrorRates) {
            $subject->reset();
            $subject->setError(false);

            foreach ($validRates as $validRate) {
                $subject->append($validRate);
            }

            return $validRates;
        }

        return $rates;
    }
}
