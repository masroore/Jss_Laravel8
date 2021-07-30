<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShipManage\ShipRegister;
use Litipk\BigNumbers\Decimal;
use DB;

class Voy extends Model
{
    use HasFactory;

    protected $table = 'tbl_voy_log';

    public function getVoyInfoByYear($shipId, $year) {
        $shipId = $shipId;
        $year = $year;

        $shipInfo = ShipRegister::where('IMO_No', $shipId)->first();

        if($shipInfo == null) return -99;

        $filterYear = substr($year, -2);
        $voyList = self::where('Ship_ID', $shipId)->whereRaw(DB::raw('mid(CP_ID, 1, 2) like ' . $filterYear))->orderBy('CP_ID', 'asc')->groupBy('CP_ID')->select('CP_ID')->get();

        if($voyList == null || count($voyList) == 0) return 0;

        $total_sail_time = Decimal::create(0);
        foreach($voyList as $key => $item) {
            $total_sail_time = $total_sail_time->add(Decimal::create($this->getVoyInfoByCP($shipId, $item->CP_ID)));
        }
        
        return round($total_sail_time->__toString(), 2);
    }

    public function getVoyInfoByCP($shipId, $voyId) {
        $beforInfo = self::where('Ship_ID', $shipId)
            ->where('CP_ID', '<', $voyId)
            ->where('Voy_Status', DYNAMIC_CMPLT_DISCH)
            ->orderBy('id', 'desc')
            ->orderBy('Voy_Date', 'desc')
            ->orderBy('Voy_Hour', 'desc')
            ->orderBy('Voy_Minute', 'desc')
            ->orderBy('GMT', 'desc')
            ->first();
        $currentTbl = self::where('Ship_ID', $shipId)
            ->where('CP_ID', $voyId)
            ->orderBy('id', 'asc')
            ->orderBy('Voy_Date', 'asc')
            ->orderBy('Voy_Hour', 'asc')
            ->orderBy('Voy_Minute', 'asc')
            ->orderBy('GMT', 'asc');

        if($beforInfo == null) {
            $tmp = $currentTbl;
            $beforInfo = $tmp->first();
            if($beforInfo == null)
                return -98;
        }

        $currentTbl = self::where('Ship_ID', $shipId)
            ->where('CP_ID', $voyId)
            ->orderBy('id', 'asc')
            ->orderBy('Voy_Date', 'asc')
            ->orderBy('Voy_Hour', 'asc')
            ->orderBy('Voy_Minute', 'asc')
            ->orderBy('GMT', 'asc');

        $currentInfo = $currentTbl->get();

        $_sailTime = 0;
        $_loadTime = 0;
        $_dischTime = 0;
        $_waitTime = 0;
        $_weatherTime = 0;
        $_repairTime = 0;
        $_supplyTime = 0;
        $_elseTime = 0;
        $_otherTime = 0;

        $start_date = $beforInfo->Voy_Date . ' ' . $beforInfo->Voy_Hour . ':' . $beforInfo->Voy_Minute . ':00';
        $start_gmt = $beforInfo->GMT;
        $total_distance = 0;

        foreach($currentInfo as $key => $item) {
            if($item->Voy_Type == DYNAMIC_SUB_SALING) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_sailTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_LOADING) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_loadTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_DISCH) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_dischTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_WAITING) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_waitTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_WEATHER) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_weatherTime  += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_REPAIR) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_repairTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_SUPPLY) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_supplyTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else if($item->Voy_Type == DYNAMIC_SUB_ELSE) {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_elseTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            } else {
                $end_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
                $_otherTime += Common::getTermDay($start_date, $end_date, $start_gmt, $item->GMT);
            }

            $start_date = $item->Voy_Date . ' ' . $item->Voy_Hour . ':' . $item->Voy_Minute . ':00';
            $start_gmt = $item->GMT;

            $total_distance += $item->Sail_Distance;
        }

        $mainInfo['sail_time'] = round($_sailTime, 2);
        $mainInfo['load_time'] = round($_loadTime, 2);
        $mainInfo['disch_time'] = round($_dischTime, 2);
        $mainInfo['wait_time'] = round($_waitTime + $_weatherTime + $_repairTime + $_supplyTime + $_elseTime, 2);

        $total_time = $_sailTime + $_loadTime + $_dischTime + $_waitTime + $_weatherTime + $_repairTime + $_supplyTime + $_elseTime + $_otherTime;
        $total_time = round($total_time, 2);

        return $total_time;

    }
}
