<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Validators\Failure;

class ZoneImport implements WithHeadingRow, SkipsOnFailure, ToArray, WithChunkReading
{
  protected $districtMap = [];

  protected $provinceMap = [];

  protected $wardMap = [];

  public function __construct()
  {
    $this->createProvinceMap();
    $this->createDistrictMap();
    $this->createWardMap();
  }

  public function onFailure(Failure ...$failures)
  { }

  public function array(array $array)
  {
    $wardImport = [];
    foreach ($array as $item) {
      if (empty($item['ma']) || empty($item['ten'])) {
        continue;
      }

      if (isset($this->wardMap[$item['ma']])) {
        continue;
      }

      $districtId = $this->getDistrictId($item);
      $wardImport[] = [
        'name' => $item['ten'],
        'gso_id' => $item['ma'],
        'district_id' => $districtId
      ];
    }

    try {
      DB::table('wards')->insert($wardImport);
    } catch (\Exception $e) {
      // Code
    }
  }

  public function chunkSize(): int
  {
    return 1000;
  }

  private function getProvinceId(array $item)
  {
    return $this->provinceMap[$item['ma_tp']] ?? $this->createProvince($item);
  }

  private function getDistrictId(array $item)
  {
    return $this->districtMap[$item['ma_qh']] ?? $this->createDistrict($item);
  }

  private function createProvince(array $item)
  {
    $provinceId = DB::table('provinces')->insertGetId([
      'name' => $item['tinh_thanh_pho'],
      'gso_id' => $item['ma_tp']
    ]);

    $this->provinceMap[$item['ma_tp']] = $provinceId;

    return $provinceId;
  }

  private function createDistrict(array $item)
  {
    $provinceId = $this->getProvinceId($item);

    $districtId = DB::table('districts')->insertGetId([
      'name' => $item['quan_huyen'],
      'gso_id' => $item['ma_qh'],
      'province_id' => $provinceId
    ]);

    $this->districtMap[$item['ma_qh']] = $districtId;

    return $provinceId;
  }

  private function createProvinceMap()
  {
    $provinces = DB::table('provinces')->get();

    $this->provinceMap = $provinces
      ->keyBy('gso_id')
      ->map(function ($item) {
        return $item->id;
      })
      ->toArray();
  }

  private function createDistrictMap()
  {
    $districts = DB::table('districts')->get();

    $this->districtMap = $districts
      ->keyBy('gso_id')
      ->map(function ($item) {
        return $item->id;
      })
      ->toArray();
  }

  private function createWardMap()
  {
    $wards = DB::table('wards')->get();

    $this->wardMap = $wards
      ->keyBy('gso_id')
      ->map(function ($item) {
        return $item->id;
      })
      ->toArray();
  }
}
