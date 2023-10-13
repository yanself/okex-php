<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Okex\Api\OkexV5;

use Lin\Okex\Request;

class Copytrading extends Request
{
    /**
     * GET /api/v5/copytrading/current-subpositions
     * */
    public function getCurrentSubpositions(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/current-subpositions';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * GET /api/v5/copytrading/subpositions-history
     * */
    public function getSubpositionsHistory(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/subpositions-history';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * POST /api/v5/copytrading/algo-order
     * */
    public function postAlgoOrder(array $data=[]){
        $this->type='POST';
        $this->path='/api/v5/copytrading/algo-order';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * POST /api/v5/copytrading/close-subposition
     * */
    public function postCloseSubposition(array $data=[]){
        $this->type='POST';
        $this->path='/api/v5/copytrading/close-subposition';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * GET /api/v5/copytrading/instruments
     * */
    public function getInstruments(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/instruments';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * POST /api/v5/copytrading/set-instruments
     * */
    public function postSetInstruments(array $data=[]){
        $this->type='POST';
        $this->path='/api/v5/copytrading/set-instruments';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * GET /api/v5/copytrading/profit-sharing-details
     * */
    public function getProfitSharingDetails(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/profit-sharing-details';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * GET /api/v5/copytrading/total-profit-sharing
     * */
    public function getTotalProfitSharing(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/total-profit-sharing';

        $this->data=$data;
        return $this->exec();
    }

    /**
     * GET /api/v5/copytrading/unrealized-profit-sharing-details
     * */
    public function getUnrealizedProfitSharingDetails(array $data=[]){
        $this->type='GET';
        $this->path='/api/v5/copytrading/unrealized-profit-sharing-details';

        $this->data=$data;
        return $this->exec();
    }
}
