import json
import glob
import os
from pyproj import Transformer

files_crs = {
    'AP_Andrafiamena Andavakoera.geojson': 'epsg:4326',
    'AP_Anjozorobe Angavavo.geojson': 'epsg:29700',
    'AP_Loky Manambato.geojson': 'epsg:4326',
    'AP_Menabe Antimena.geojson': 'epsg:32738',
    'AP_Allee de baobabs.geojson': 'epsg:32638'
}

transformers = {}
for crs in set(files_crs.values()):
    if crs != 'epsg:4326':
        transformers[crs] = Transformer.from_crs(crs, 'epsg:4326', always_xy=True)

output = {}

for filepath in glob.glob('Site_Fanamby/*.geojson'):
    filename = os.path.basename(filepath)
    if filename not in files_crs:
        continue
    
    crs = files_crs[filename]
    with open(filepath, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    # Extract coordinates
    geom = data['features'][0]['geometry']
    if geom['type'] == 'MultiPolygon':
        ring = geom['coordinates'][0][0]
    elif geom['type'] == 'Polygon':
        ring = geom['coordinates'][0]
    else:
        continue
    
    transformed_ring = []
    for pt in ring:
        x, y = pt[0], pt[1]
        if crs != 'epsg:4326':
            x, y = transformers[crs].transform(x, y)
        transformed_ring.append({'lat': y, 'lng': x})
    
    site_name = filename.replace('AP_', '').replace('.geojson', '').strip()
    
    # Quick fix names to match seeder names
    if site_name == 'Andrafiamena Andavakoera':
        site_name = 'Andrafiamena - Andavakoera'
    elif site_name == 'Anjozorobe Angavavo':
        site_name = 'Anjozorobe - Angavo'
    elif site_name == 'Loky Manambato':
        site_name = 'Loky - Manambato'
    elif site_name == 'Allee de baobabs':
        site_name = 'Allee des Baobabs'
        
    output[site_name] = transformed_ring

with open('database/seeders/site_coordinates.json', 'w', encoding='utf-8') as f:
    json.dump(output, f)
    
print("Processed", len(output), "sites.")
