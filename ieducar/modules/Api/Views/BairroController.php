<?php

use App\Models\Place;

class BairroController extends ApiCoreController
{
    protected function getNeighborhoods()
    {
        return Place::query()
            ->select('neighborhood')
            ->whereUnaccent('neighborhood', $this->getQueryString('query'))
            ->groupBy('neighborhood')
            ->orderBy('neighborhood')
            ->limit(15)
            ->pluck('neighborhood', 'neighborhood')
            ->all();
    }

    public function Gerar()
    {
        if ($this->isRequestFor('get', 'bairro-search')) {
            $this->appendResponse([
                'result' => $this->getNeighborhoods(),
            ]);
        } else {
            $this->notImplementedOperationError();
        }
    }
}
