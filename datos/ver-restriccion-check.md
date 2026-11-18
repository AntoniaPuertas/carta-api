# Cómo ver la restricción CHECK en phpMyAdmin

La tabla `platos` tiene una restricción `CHECK` sobre la columna `precio`:

```sql
precio DECIMAL(6,2) NOT NULL CHECK (precio > 0)
```

phpMyAdmin no muestra las restricciones `CHECK` en la pestaña **Estructura**, así que para verla hay que consultar la definición completa de la tabla.

## Pasos

1. Abre phpMyAdmin y selecciona la base de datos `carta` en el panel izquierdo.
2. Ve a la pestaña **SQL**.
3. Escribe la siguiente consulta y pulsa **Continuar**:

   ```sql
   SHOW CREATE TABLE platos;
   ```

4. En el resultado, la columna `Create Table` aparece recortada (termina en `...`). Para verla entera:
   - Pulsa en **+ Opciones**, encima de la tabla de resultados.
   - Marca **Textos completos**.
   - Pulsa **Continuar**.

5. Ahora se ve la sentencia `CREATE TABLE` completa, incluida la restricción, con un aspecto parecido a este:

   ```sql
   CONSTRAINT `platos_chk_1` CHECK (`precio` > 0)
   ```

## Comprobar que funciona

Si intentas insertar un plato con precio 0 o negativo, MySQL/MariaDB rechazará la inserción:

```sql
INSERT INTO platos (nombre, categoria, precio) VALUES ('Prueba', 'postre', -5);
-- Error: Check constraint 'platos_chk_1' is violated.
```
