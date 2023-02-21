Every schema represents a table in the database. The schema name is the table name. The schema file name is the table
name with the extension `.json`.

## Schema file format

The schema file is a JSON file. The file contains an object with the following properties:

| Property | Description                                                                |
|----------|----------------------------------------------------------------------------|
| `name` | The name of the table.                                                     |
| `title` | The title of the table in human readable form.                             |
| `fields` | An array with the column names as keys and the column definition as value. |
