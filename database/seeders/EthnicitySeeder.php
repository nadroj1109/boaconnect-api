<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Ethnicity;

class EthnicitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ethnicities = [
            'AETA',
            'AGTA',
            'ATI',
            'AYTA MAG-ANTSI',
            'AYTA MAGBUKON',
            'AYTA MAG-INDI',
            'AYTA ABELLEN',
            'BADJAO',
            'BAGOBO',
            'BAGO',
            'BALANGAO',
            'BATAK',
            "B'LAAN",
            'BUGKALOT',
            'BUKIDNON',
            'BONTOC',
            'DUMAGAT',
            'GADDANG',
            'HANUNUO MANGYAN',
            'HIGAONON',
            'ILONGOT',
            'IFUGAO',
            'IRAYA MANGYAN',
            'ISNEG',
            'ITAWIS',
            'IVATAN',
            'IWAK',
            'JAMA MAPUN',
            'KABIHUG',
            'KALAGAN',
            'KALANGUYA',
            'KALINGA',
            'KANKANAEY',
            'KAOLO',
            "KE'NEY",
            'KINARAY-A',
            'KOLIBUGAN',
            'KAGAYANEN',
            'LAMBANGIAN',
            'LANGILAN MANOBO',
            'MAGUINDANAO',
            'MANDAYA',
            'MAMANWA',
            'MANSAKA',
            'MANOBO',
            'MANGYAN',
            'MATIGSALUG',
            'MOLBOG',
            'PALAWANO',
            'PANAY BUKIDNON',
            "PALA'WAN",
            'PANKALIS',
            'REMONTADO',
            'SAMA BANGUINGUI',
            'SAMA DILAUT',
            'SUBANON',
            'TAGBANWA',
            'TAGAKAULO',
            'TEDURAY',
            "T'BOLI",
            'TALAANDIG',
            "TAU'T BATU",
            'TINGGUIAN',
            'TINGGIAN',
            'TUMANDOK',
            'UBO',
            'YAKAN',
            'OTHER LOCAL ETHNICITY',
            'OTHER FOREIGN ETHNICITY',
            'NOT REPORTED',
        ];

        foreach ($ethnicities as $ethnicity) {
            Ethnicity::updateOrCreate(
                ['ethnicity' => $ethnicity],
                ['is_active' => true]
            );
        }
    }
}
