# Frequent Location Imports

CSV files in this folder are prepared for the BorderReach admin **Locations** importer.

Required columns:

```csv
country,name,district,admin_area,category,aliases,sort_order
```

- `country`: accepts `SLE`, `GIN`, or `LBR`.
- `name`: the town, border post, or common travel location shown to officers.
- `district`: the Sierra Leone border district that should receive this choice on mobile sync, such as `Kambia`, `Kailahun`, `Falaba`, or `Pujehun`.
- `admin_area`: use this to describe the corridor or administrative context shown in the option label.
- `category`: describes the location type, such as border post, border town, district hub, or nearby town.
- `aliases`: alternate spellings officers may use.
- `sort_order`: controls the order shown in mobile form choices.

Upload these files from **Admin > Locations > Upload Location List**.
