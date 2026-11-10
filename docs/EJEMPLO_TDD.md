# Ejemplo de TDD: así se habría construido `mad`

El método `mad` (error absoluto medio) ya existe en `src/CalculadoraPronosticos.php`. Este documento reconstruye, paso a paso, cómo se habría escrito con TDD: primero la prueba, después el código mínimo para que pase y al final la mejora del diseño. Es el mismo ciclo que van a seguir en la actividad, aplicado a un método que ya conocen.

**De dónde salen las salidas.** Cada salida de consola de este documento se obtuvo de verdad, en una copia del proyecto (`/tmp/pronosticos-copia/pronosticos-php`) de la que se quitaron `mad` y sus pruebas. Se copiaron tal cual, sin los colores de la terminal y sin la barra de progreso de PHPStan; en su equipo solo cambian la ruta de la carpeta, los tiempos y la memoria. El código del proyecto no se modificó para escribir este documento.

## El requisito

Antes de escribir una sola línea hay que saber qué se espera. Producción lo describió así:

| Regla | Ejemplo |
|---|---|
| El MAD es el promedio de las diferencias absolutas entre la demanda real y el pronóstico, en unidades | real `[141, 138, 150]`, pronóstico `[126.67, 133.67, 135.67]` → (14.33 + 4.33 + 14.33) / 3 = 11.00 |
| Si el pronóstico es exacto, el MAD es 0 | real `[100, 100]`, pronóstico `[100, 100]` → 0 |
| Las dos series deben tener el mismo número de periodos | `[100, 110]` y `[100]` → `InvalidArgumentException` "Las series deben tener el mismo número de periodos" |
| Se necesita al menos un periodo | `[]` y `[]` → `InvalidArgumentException` "Se necesita al menos un periodo" |

Cada fila de la tabla se convierte en un ciclo rojo-verde-refactor.

---

## Ciclo 1. El cálculo con el ejemplo de Producción

### Rojo

La prueba describe el primer comportamiento con el ejemplo calculado a mano. Se crea `tests/Unit/MadTest.php`:

```php
<?php

declare(strict_types=1);

namespace Pronosticos\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pronosticos\CalculadoraPronosticos;

/** Pruebas unitarias del error absoluto medio (MAD). */
final class MadTest extends TestCase
{
    private CalculadoraPronosticos $calculadora;

    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraPronosticos();
    }

    public function testCalculaElErrorAbsolutoMedio(): void
    {
        $resultado = $this->calculadora->mad([141, 138, 150], [126.67, 133.67, 135.67]);

        // (14.33 + 4.33 + 14.33) / 3 = 11.00, calculado de forma independiente.
        $this->assertEqualsWithDelta(11.00, $resultado, 0.01);
    }
}
```

```bash
composer test:unit
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

E.....                                                              6 / 6 (100%)

Time: 00:00.002, Memory: 16.00 MB

There was 1 error:

1) Pronosticos\Tests\Unit\MadTest::testCalculaElErrorAbsolutoMedio
Error: Call to undefined method Pronosticos\CalculadoraPronosticos::mad()

/private/tmp/pronosticos-copia/pronosticos-php/tests/Unit/MadTest.php:23

ERRORS!
Tests: 6, Assertions: 7, Errors: 1.
Script phpunit --testsuite unit handling the test:unit event returned with error code 2
```

PHPUnit distingue un **error** (la prueba no pudo ejecutarse: `E`, código de salida 2) de un **fallo** (se ejecutó y una aserción no se cumplió: `F`, código 1). Aquí el método no existe, así que es un error. Es un rojo legítimo. Las otras cinco pruebas, las de `promedioMovil`, siguen pasando.

### Verde

El código **mínimo** para que esa prueba pase es devolver el valor esperado:

```php
    public function mad(array $real, array $pronostico): float
    {
        return 11.0;
    }
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

......                                                              6 / 6 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (6 tests, 8 assertions)
```

### Refactor

Todavía no hay nada que mejorar: el método tiene una sola línea y no repite nada.

### Qué se aprende

- Ver el rojo primero demuestra que la prueba es capaz de fallar. Una prueba que nunca se vio fallar puede estar pasando por accidente (por ejemplo, porque no se ejecuta).
- Devolver una constante parece hacer trampa, pero es deliberado: obliga a que **otra prueba** exija el cálculo real. Si con una sola prueba se escribe la fórmula completa, nada garantiza que cada parte de la fórmula esté verificada.

---

## Ciclo 2. Un segundo caso obliga a calcular de verdad

### Rojo

```php
    public function testEsCeroCuandoElPronosticoEsExacto(): void
    {
        $this->assertSame(0.0, $this->calculadora->mad([100, 100], [100, 100]));
    }
```

```bash
composer test:unit
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

.F.....                                                             7 / 7 (100%)

Time: 00:00.001, Memory: 16.00 MB

There was 1 failure:

1) Pronosticos\Tests\Unit\MadTest::testEsCeroCuandoElPronosticoEsExacto
Failed asserting that 11.0 is identical to 0.0.

/private/tmp/pronosticos-copia/pronosticos-php/tests/Unit/MadTest.php:31

FAILURES!
Tests: 7, Assertions: 9, Failures: 1.
Script phpunit --testsuite unit handling the test:unit event returned with error code 1
```

Ahora sí la prueba se ejecuta y falla: la constante 11.0 no sirve para este caso.

### Verde

Con dos ejemplos distintos, el único código que pasa los dos es el cálculo real. `array_map` recorre las dos series a la vez y aplica la función a cada par de valores:

```php
    public function mad(array $real, array $pronostico): float
    {
        $errores = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($errores) / count($errores);
    }
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

.......                                                             7 / 7 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (7 tests, 9 assertions)
```

### Refactor

El código es corto y claro. Se deja como está hasta tener las validaciones.

### Qué se aprende

- Esta técnica se llama **triangulación**: cada ejemplo nuevo descarta una implementación falsa. Dos ejemplos que no pueden resolverse con la misma constante obligan a escribir la fórmula.
- PHPUnit muestra el valor obtenido (`11.0`) y el esperado (`0.0`), y la línea de la prueba. Por eso conviene que cada prueba verifique un solo comportamiento: el mensaje dice exactamente qué falló.

---

## Ciclo 3. Series de distinta longitud

### Rojo

```php
    public function testRechazaSeriesDeDistintaLongitud(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mismo número de periodos');

        $this->calculadora->mad([100, 110], [100]);
    }
```

```bash
composer test:unit
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

..F.....                                                            8 / 8 (100%)

Time: 00:00.001, Memory: 16.00 MB

There was 1 failure:

1) Pronosticos\Tests\Unit\MadTest::testRechazaSeriesDeDistintaLongitud
Failed asserting that exception of type "TypeError" matches expected exception "InvalidArgumentException". Message was: "Pronosticos\CalculadoraPronosticos::{closure:Pronosticos\CalculadoraPronosticos::mad():42}(): Argument #2 ($valorPronosticado) must be of type int|float, null given" at
/private/tmp/pronosticos-copia/pronosticos-php/src/CalculadoraPronosticos.php:42
/private/tmp/pronosticos-copia/pronosticos-php/src/CalculadoraPronosticos.php:41
/private/tmp/pronosticos-copia/pronosticos-php/tests/Unit/MadTest.php:39
.

FAILURES!
Tests: 8, Assertions: 10, Failures: 1.
Script phpunit --testsuite unit handling the test:unit event returned with error code 1
```

Hubo una excepción, pero no la acordada. Cuando las series tienen distinto tamaño, `array_map` completa la más corta con `null`; como el archivo declara `strict_types=1` y la función exige `int|float`, PHP lanza un `TypeError`. El mensaje habla de una función anónima interna: para quien llama al método no explica qué hizo mal.

### Verde

```php
    public function mad(array $real, array $pronostico): float
    {
        if (count($real) !== count($pronostico)) {
            throw new InvalidArgumentException('Las series deben tener el mismo número de periodos');
        }
        $errores = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($errores) / count($errores);
    }
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

........                                                            8 / 8 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (8 tests, 11 assertions)
```

### Refactor

Aún nada: hay una sola validación.

### Qué se aprende

- Las pruebas de los casos inválidos no son un trámite: definen qué error recibe quien usa el método. `strict_types` evitó un resultado equivocado, pero el contrato exige un mensaje que el usuario entienda.
- `expectException` y `expectExceptionMessage` se declaran **antes** de la llamada que debe fallar.

---

## Ciclo 4. Series vacías

### Rojo

```php
    public function testRechazaSeriesVacias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('al menos un periodo');

        $this->calculadora->mad([], []);
    }
```

```bash
composer test:unit
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

...F.....                                                           9 / 9 (100%)

Time: 00:00.002, Memory: 16.00 MB

There was 1 failure:

1) Pronosticos\Tests\Unit\MadTest::testRechazaSeriesVacias
Failed asserting that exception of type "DivisionByZeroError" matches expected exception "InvalidArgumentException". Message was: "Division by zero" at
/private/tmp/pronosticos-copia/pronosticos-php/src/CalculadoraPronosticos.php:50
/private/tmp/pronosticos-copia/pronosticos-php/tests/Unit/MadTest.php:47
.

FAILURES!
Tests: 9, Assertions: 12, Failures: 1.
Script phpunit --testsuite unit handling the test:unit event returned with error code 1
```

Otra vez una excepción que no es la acordada: `DivisionByZeroError`, porque `count([])` es 0.

### Verde

```php
    public function mad(array $real, array $pronostico): float
    {
        if (count($real) !== count($pronostico)) {
            throw new InvalidArgumentException('Las series deben tener el mismo número de periodos');
        }
        if ($real === []) {
            throw new InvalidArgumentException('Se necesita al menos un periodo');
        }
        $errores = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($errores) / count($errores);
    }
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

.........                                                           9 / 9 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (9 tests, 13 assertions)
```

### Qué se aprende

- La prueba no solo exige que haya un error: exige **el** error acordado, con su tipo y su mensaje. Eso es parte del contrato del método.
- Con este ciclo la tabla del requisito queda cubierta. Ahora se puede mejorar el diseño con la tranquilidad de que cuatro pruebas vigilan el comportamiento.

---

## Refactor final

Antes de mejorar el código conviene preguntarle al análisis estático. Las pruebas pasan, pero PHPStan no está conforme:

```bash
composer lint
```

```
Note: Using configuration file /private/tmp/pronosticos-copia/pronosticos-php/phpstan.neon.

 ------ -----------------------------------------------------------------------
  Line   src/CalculadoraPronosticos.php
 ------ -----------------------------------------------------------------------
  39     Method Pronosticos\CalculadoraPronosticos::mad() has parameter
         $pronostico with no value type specified in iterable type array.
         🪪  missingType.iterableValue
         💡  See:
         https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-i
         terable-type
  39     Method Pronosticos\CalculadoraPronosticos::mad() has parameter $real
         with no value type specified in iterable type array.
         🪪  missingType.iterableValue
         💡  See:
         https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-i
         terable-type
 ------ -----------------------------------------------------------------------


 [ERROR] Found 2 errors

Script phpstan analyse handling the lint event returned with error code 1
```

En el nivel 6, PHPStan exige que los arreglos documenten qué contienen. Con eso y con lo que se ve en el código, el refactor queda así, **sin cambiar lo que hace el método**:

1. **Tipos de los arreglos.** El bloque `@param list<int|float>` dice que las series son listas de números. PHPStan lo usa para revisar cada llamada al método.
2. **Quitar la duplicación.** Las dos validaciones son idénticas a las que necesita `mape`, que ya las tiene en `validarSeries`. Se reutiliza ese método en lugar de mantener dos copias de la misma regla.
3. **Nombres que explican.** `$errores` pasa a ser `$erroresAbsolutos`: el nombre dice qué contiene.

Antes:

```php
    public function mad(array $real, array $pronostico): float
    {
        if (count($real) !== count($pronostico)) {
            throw new InvalidArgumentException('Las series deben tener el mismo número de periodos');
        }
        if ($real === []) {
            throw new InvalidArgumentException('Se necesita al menos un periodo');
        }
        $errores = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($errores) / count($errores);
    }
```

Después (es exactamente el código que hoy tiene el proyecto):

```php
    /**
     * Error absoluto medio (MAD): promedio de las diferencias absolutas, en unidades.
     *
     * @param list<int|float> $real
     * @param list<int|float> $pronostico
     */
    public function mad(array $real, array $pronostico): float
    {
        $this->validarSeries($real, $pronostico);

        $erroresAbsolutos = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($erroresAbsolutos) / count($erroresAbsolutos);
    }
```

Después del refactor se corren todas las pruebas unitarias, porque `validarSeries` también la usa `mape`:

```bash
composer test:unit
```

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /private/tmp/pronosticos-copia/pronosticos-php/phpunit.xml

.........                                                           9 / 9 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (9 tests, 13 assertions)
```

Y el análisis estático otra vez:

```bash
composer lint
```

```
Note: Using configuration file /private/tmp/pronosticos-copia/pronosticos-php/phpstan.neon.


 [OK] No errors
```

### Qué se aprende

- El refactor es seguro **porque** hay pruebas. Sin ellas, cambiar las validaciones por `validarSeries` sería una apuesta.
- Las pruebas y el análisis estático se complementan: las pruebas confirmaron que el método calcula bien; PHPStan señaló lo que las pruebas no ven, los tipos que faltaban.
- Refactorizar no es agregar funciones nuevas. Si durante el refactor aparece un comportamiento nuevo, se vuelve al rojo con una prueba para ese comportamiento.

---

## El historial que deja el ciclo

En la actividad se pide que el historial de commits muestre las tres fases. Para `mad`, un historial razonable sería:

| Commit | Fase | Estado de las pruebas |
|---|---|---|
| `test: prueba en rojo para mad` | Rojo del ciclo 1 | 1 con error |
| `feat: mad devuelve el resultado del ejemplo` | Verde del ciclo 1 | todas pasan |
| `test: mad es cero con un pronóstico exacto` | Rojo del ciclo 2 | 1 falla |
| `feat: mad calcula el promedio de los errores absolutos` | Verde del ciclo 2 | todas pasan |
| `test: mad rechaza series de distinta longitud` | Rojo del ciclo 3 | 1 falla |
| `feat: mad valida la longitud de las series` | Verde del ciclo 3 | todas pasan |
| `test: mad rechaza series vacías` | Rojo del ciclo 4 | 1 falla |
| `feat: mad exige al menos un periodo` | Verde del ciclo 4 | todas pasan |
| `refactor: mad documenta sus tipos y reutiliza validarSeries` | Refactor | todas pasan |

No es obligatorio un commit por fase de cada ciclo; lo importante es que el historial permita ver que la prueba existió antes que el código.

## Resumen

| Fase | Qué se hace | Cómo se sabe que terminó |
|---|---|---|
| Rojo | Se escribe una prueba para un comportamiento que todavía no existe | La prueba falla, y falla por la razón esperada |
| Verde | Se escribe el código mínimo para que pase | Todas las pruebas pasan |
| Refactor | Se mejora el diseño sin cambiar el comportamiento | Todas las pruebas siguen pasando y el análisis estático no reporta nada |
