# Frequent Location Imports

CSV files in this folder are prepared for the BorderReach admin **Locations** importer.

Required columns:

```csv
code,country,name,district,admin_area,category,aliases,sort_order
```

- `code`: stable value stored in Android drafts, synced submissions, API payloads, and exports. Keep this stable after deployment.
- `country`: accepts `SLE`, `GIN`, or `LBR`.
- `name`: the town, border post, or common travel location shown to officers.
- `district`: the Sierra Leone border district that should receive this choice on mobile sync, such as `Kambia`, `Kailahun`, `Falaba`, or `Pujehun`.
- `admin_area`: use this to describe the corridor or administrative context shown in the option label.
- `category`: describes the location type, such as border post, border town, district hub, or nearby town.
- `aliases`: alternate spellings officers may use.
- `sort_order`: controls the order shown in mobile form choices.

Use the same upload for **From Location** and **To Location**. Include both sides of a corridor in the file: for example, Gbalamuya/Kukuna on the Sierra Leone side and Pamelap/Forecariah/Kindia/Conakry on the Guinea side, all tied to district `Kambia`.

Upload these files from **Admin > Locations > Upload Location List**.
