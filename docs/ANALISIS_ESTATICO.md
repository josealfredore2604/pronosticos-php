# Análisis estático con PHPStan

Guía de referencia para entender, ejecutar y modificar el análisis estático de este proyecto. Todas las salidas de consola que aparecen aquí se obtuvieron ejecutando los comandos en una copia del proyecto (`/tmp/pronosticos-copia/pronosticos-php`), con PHPStan 2.3.5. Se muestran sin los colores de la terminal y sin la barra de progreso. Los archivos de ejemplo (`ejemplos/…`, `cambios/…`) se crearon solo para provocar cada hallazgo y no forman parte del repositorio.

## 1. Qué es el análisis estático

El análisis estático revisa el código **sin ejecutarlo**. Lee el texto del programa y lo compara con un conjunto de reglas: métodos que no existen, tipos que no coinciden, variables que podrían no estar definidas, código que nunca se ejecuta.

Las pruebas (análisis dinámico) ejecutan el código y comparan el resultado con lo esperado. Por eso solo encuentran problemas en las líneas que recorren. El análisis estático encuentra lo que las pruebas no ven:

| Lo encuentra el análisis estático | Por qué las pruebas no lo ven |
|---|---|
| Un método mal escrito en una rama que ninguna prueba recorre (por ejemplo, dentro de un `catch`) | La línea nunca se ejecuta durante las pruebas |
| Un método que declara devolver `float` y en un caso devuelve `string` | Las pruebas solo revisan los casos que alguien pensó |
| Una variable que solo se define dentro de un `if` | Falla únicamente cuando la condición es falsa |
| Un arreglo sin documentar qué contiene | Funciona, pero quien lo usa no sabe qué puede pasarle |

El temario ISTQB CTFL v4.0 (sección 3.1.3) lo resume así: el análisis estático encuentra defectos directamente, puede detectar defectos en caminos que se ejecutan rara vez y puede medir características que no dependen de ejecutar el código, como la mantenibilidad. Las dos técnicas se complementan; ninguna reemplaza a la otra.

## 2. La herramienta y su configuración

[PHPStan](https://phpstan.org/) es un analizador estático de PHP centrado en los **tipos**: deduce qué tipo tiene cada expresión y comprueba que cada uso sea coherente. Está en `require-dev` de `composer.json` y se ejecuta con `composer lint` (que corre `phpstan analyse`).

La configuración está en `phpstan.neon`, en la sección `parameters`:

```neon
parameters:
    level: 6
    paths:
        - src
        - tests
```

| Parámetro | Qué hace |
|---|---|
| `level: 6` | Qué tan estricto es el análisis. Hay 11 niveles, del 0 (lo más básico) al 10 (lo más estricto). Los niveles son acumulativos: el nivel 6 incluye todo lo de los niveles 0 a 5 |
| `paths` | Qué carpetas se analizan. Las rutas son relativas a la carpeta de `phpstan.neon` |

A diferencia de otros analizadores, en PHPStan las reglas no se activan una por una: se elige un nivel. Por eso, en este proyecto, "cada regla configurada" significa cada nivel del 0 al 6.

## 3. Las reglas configuradas: los niveles 0 a 6

Para cada nivel se creó un archivo en `ejemplos/` y se comprobó que PHPStan **no** reporta nada con el nivel anterior y **sí** lo reporta con ese nivel (`vendor/bin/phpstan analyse --level N ejemplos/NivelN.php`). La salida que se muestra es la del nivel 6 del proyecto. Todas las correcciones se guardaron en archivos y PHPStan terminó con `[OK] No errors`.

### Nivel 0: comprobaciones básicas

Clases, funciones y métodos de `$this` que no existen, número de argumentos incorrecto y variables que nunca están definidas.

```php
final class Nivel0
{
    public function pronosticar(): float
    {
        return $this->promedioMovl([120, 132, 128], 3);
    }
}
```

```
 ------ ----------------------------------------------
  Line   Nivel0.php
 ------ ----------------------------------------------
  11     Call to an undefined method
         Pronosticos\Ejemplos\Nivel0::promedioMovl().
         🪪  method.notFound
 ------ ----------------------------------------------
```

Corrección: llamar al método que existe, con su nombre correcto: `(new CalculadoraPronosticos())->promedioMovil([120, 132, 128], 3)`.

### Nivel 1: variables que podrían no estar definidas

Variables que solo se definen en algunos caminos, y métodos o propiedades "mágicos" (`__call`, `__get`) desconocidos.

```php
    public function mensaje(bool $hayAlerta): string
    {
        if ($hayAlerta) {
            $texto = 'Revisar el plan de producción';
        }

        return $texto;
    }
```

```
  15     Variable $texto might not be defined.
         🪪  variable.undefined
```

Si `$hayAlerta` es `false`, `$texto` no existe. Corrección: darle un valor antes del `if`.

```php
        $texto = 'Sin novedades';
        if ($hayAlerta) {
            $texto = 'Revisar el plan de producción';
        }
```

### Nivel 2: métodos desconocidos en cualquier objeto y PHPDoc válidos

El nivel 0 solo revisa los métodos de `$this`. El nivel 2 revisa los métodos de cualquier expresión cuyo tipo conoce, y valida los bloques PHPDoc.

```php
    public function pronosticar(CalculadoraPronosticos $calculadora): float
    {
        return $calculadora->promedioMovl([120, 132, 128], 3);
    }
```

```
  13     Call to an undefined method
         Pronosticos\CalculadoraPronosticos::promedioMovl().
         🪪  method.notFound
```

Corrección: `$calculadora->promedioMovil(...)`.

### Nivel 3: tipos de retorno y de propiedades

Que lo que se devuelve coincida con el tipo declarado, y que lo que se asigna a una propiedad coincida con su tipo.

```php
    public function formatear(float $pronostico): float
    {
        return number_format($pronostico, 2);
    }
```

```
  11     Method Pronosticos\Ejemplos\Nivel3::formatear() should return float
         but returns string.
         🪪  return.type
```

`number_format` devuelve un texto. Corrección: declarar el retorno que de verdad tiene, `: string`.

### Nivel 4: código muerto

Código que nunca se ejecuta, `instanceof` que siempre es falso, `else` que nunca se alcanza.

```php
    public function validarVentana(int $ventana): bool
    {
        if ($ventana <= 0) {
            return false;
            echo 'Ventana inválida';
        }

        return true;
    }
```

```
  13     Unreachable statement - code above always terminates.
         🪪  deadCode.unreachable
```

Corrección: el `echo` va antes del `return`.

### Nivel 5: tipos de los argumentos

Que cada argumento tenga el tipo que espera el parámetro.

```php
    public function pronosticar(CalculadoraPronosticos $calculadora, string $ventana): float
    {
        return $calculadora->promedioMovil([120, 132, 128], $ventana);
    }
```

```
  13     Parameter #2 $ventana of method
         Pronosticos\CalculadoraPronosticos::promedioMovil() expects int,
         string given.
         🪪  argument.type
```

Con `declare(strict_types=1)`, este código lanzaría un `TypeError` al ejecutarse; PHPStan lo avisa antes. Corrección: que el parámetro sea `int $ventana`, y que la conversión de texto a número se haga una sola vez, donde el dato entra al sistema (por ejemplo, en `public/index.php`). Agregar `(int)` en cada llamada solo para callar el aviso esconde el problema.

### Nivel 6: tipos que faltan

Parámetros, retornos y propiedades sin tipo, y arreglos que no documentan qué contienen. Es el nivel que más se nota en este proyecto: por eso cada método que recibe una serie tiene `@param list<int|float>`.

```php
    public function total(array $serie): float
    {
        return array_sum($serie);
    }
```

```
 ------ -----------------------------------------------------------------------
  Line   Nivel6.php
 ------ -----------------------------------------------------------------------
  9      Method Pronosticos\Ejemplos\Nivel6::total() has parameter $serie with
         no value type specified in iterable type array.
         🪪  missingType.iterableValue
         💡  See:
         https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-i
         terable-type
 ------ -----------------------------------------------------------------------
```

Corrección: documentar el contenido del arreglo.

```php
    /**
     * @param list<int|float> $serie
     */
    public function total(array $serie): float
```

`list<int|float>` significa "arreglo con índices 0, 1, 2… cuyos valores son enteros o decimales". Con eso PHPStan puede revisar cada llamada al método.

### Los niveles 7 a 10

No están activos en el proyecto, pero conviene saber qué agregan: el 7, uniones de tipos parcialmente incorrectas; el 8, llamadas a métodos sobre valores que pueden ser `null`; el 9, uso explícito de `mixed`; el 10, uso implícito de `mixed`. En la sección 6 se prueba el nivel 8.

## 4. Cómo se ejecuta y cómo se lee un hallazgo

```bash
composer lint                                   # analiza src y tests (phpstan analyse)
vendor/bin/phpstan analyse src/                 # solo una carpeta
vendor/bin/phpstan analyse --level 8            # con otro nivel, sin editar phpstan.neon
```

Un hallazgo en el formato por defecto (tabla):

```
Note: Using configuration file /private/tmp/pronosticos-copia/pronosticos-php/phpstan.neon.

 ------ ----------------------------------------------
  Line   Nivel0.php
 ------ ----------------------------------------------
  11     Call to an undefined method
         Pronosticos\Ejemplos\Nivel0::promedioMovl().
         🪪  method.notFound
 ------ ----------------------------------------------


 [ERROR] Found 1 error
```

| Parte | En el ejemplo | Qué significa |
|---|---|---|
| Archivo | `Nivel0.php` | Encabezado de la tabla: todos los hallazgos de ese archivo |
| Línea | `11` | Columna `Line` |
| Mensaje | `Call to an undefined method…` | Qué encontró, con el nombre concreto |
| Identificador | `🪪 method.notFound` | Nombre del tipo de error. Se usa para buscarlo (https://phpstan.org/error-identifiers) y para suprimirlo (sección 6) |
| Pista | `💡 See: …` | Enlace con la explicación, cuando PHPStan tiene una |
| Corrección automática | `🔧` | Aparece al final del mensaje cuando el error se puede corregir con `--fix` |
| Resumen | `[ERROR] Found 1 error` | Total de errores. Sin hallazgos: `[OK] No errors` |

### El código de salida

El pipeline no lee los mensajes: decide con el **código de salida** del comando. La documentación de PHPStan establece que 0 significa que no hay errores; en todas las ejecuciones de esta guía, cuando hubo errores el código fue 1. Composer devuelve el mismo código y lo avisa en una línea:

```
Script phpstan analyse handling the lint event returned with error code 1
```

En GitHub Actions, un paso cuyo comando termina con un código distinto de 0 falla, el job queda en rojo y los jobs que dependen de él (`needs`) no se ejecutan.

## 5. Cómo corregir

### Lo que PHPStan corrige solo

Desde la versión 2.3.5, algunas reglas saben corregir lo que reportan: sus errores llevan el símbolo 🔧 y se corrigen con `--fix`. Un caso frecuente es una supresión que ya no hace falta (ver sección 6):

```php
    public function ventanaPorDefecto(): int
    {
        return 3; // @phpstan-ignore return.type (se dejó de cuando devolvía texto)
    }
```

```bash
vendor/bin/phpstan analyse cambios/Sobra.php
```

```
 ------ -----------------------------------------------------------------
  Line   Sobra.php
 ------ -----------------------------------------------------------------
  11     No error with identifier return.type is reported on line 11. 🔧
         🪪  ignore.unmatchedIdentifier (non-ignorable)
 ------ -----------------------------------------------------------------


 [ERROR] Found 1 error

🔧 1 error can be fixed automatically. Run PHPStan again with --fix.
```

```bash
vendor/bin/phpstan analyse cambios/Sobra.php --fix
```

```
Analysing files...

🔧 Round 1: fixed 1 error in 1 file

Analysing fixed code (round 2)...


 [OK] 1 error fixed in 1 file
```

PHPStan borró el comentario y la línea quedó `return 3;`. Analiza el código corregido otra vez, en rondas, hasta que no queda nada que corregir; el código de salida es 0 si corrigió todo lo corregible y 1 si quedó algo.

### Lo que hay que corregir a mano

Ninguno de los errores de la sección 3 tiene 🔧. Son errores de tipos y de lógica: qué tipo declarar, qué valor inicial dar, dónde convertir un dato. Esas correcciones se hacen a mano y se verifican corriendo de nuevo las pruebas y el análisis.

## 6. Cómo cambiar la configuración

Todos los cambios de esta sección se probaron en una copia del proyecto y después se revirtieron.

### Subir o bajar el nivel

El "umbral" de PHPStan es `level`. El código del proyecto pasa todos los niveles, del 6 al 10 (se comprobó con `vendor/bin/phpstan analyse --level 7`, 8, 9 y 10). Para ver qué cambia al subirlo, este ejemplo usa un parámetro que puede ser `null`:

```php
    public function mensaje(?InvalidArgumentException $error): string
    {
        return $error->getMessage();
    }
```

Con `level: 6`, `vendor/bin/phpstan analyse cambios/Nulos.php` responde `[OK] No errors`. Con `level: 8` en `phpstan.neon`:

```
 ------ -------------------------------------------------------------------
  Line   Nulos.php
 ------ -------------------------------------------------------------------
  13     Cannot call method getMessage() on InvalidArgumentException|null.
         🪪  method.nonObject
 ------ -------------------------------------------------------------------


 [ERROR] Found 1 error
```

Si `$error` es `null`, la llamada falla. El nivel 8 obliga a tratar ese caso. Subir el nivel endurece el análisis para todo el código; bajarlo lo relaja. Es una decisión del equipo y conviene anotarla en el README.

### Agregar una regla

Además del nivel, PHPStan tiene comprobaciones que se activan una por una con parámetros. Por ejemplo, `checkUninitializedProperties` (desactivada por defecto) reporta las propiedades con tipo que no se inicializan en el constructor:

```neon
parameters:
    level: 6
    paths:
        - src
        - tests
    checkUninitializedProperties: true
```

Con esta clase, en la que `$umbral` solo recibe un valor si alguien llama antes a `configurarUmbral`:

```php
final class Propiedades
{
    private float $umbral;

    public function configurarUmbral(float $umbral): void
    {
        $this->umbral = $umbral;
    }

    public function debeAlertar(float $error): bool
    {
        return $error >= $this->umbral;
    }
}
```

```
 ------ ---------------------------------------------------------------------
  Line   Propiedades.php
 ------ ---------------------------------------------------------------------
  9      Class Pronosticos\Cambios\Propiedades has an uninitialized property
         $umbral. Give it default value or assign it in the constructor.
         🪪  property.uninitialized
 ------ ---------------------------------------------------------------------
```

Sin el parámetro, el mismo archivo pasa sin errores. Al activarlo en el proyecto completo aparecen además dos hallazgos en las pruebas:

```
 ------ ----------------------------------------------------------------------
  Line   tests/Unit/MadTest.php
 ------ ----------------------------------------------------------------------
  14     Class Pronosticos\Tests\Unit\MadTest has an uninitialized property
         $calculadora. Give it default value or assign it in the constructor.
         🪪  property.uninitialized
 ------ ----------------------------------------------------------------------

 ------ ---------------------------------------------------------------------
  Line   tests/Unit/PromedioMovilTest.php
 ------ ---------------------------------------------------------------------
  22     Class Pronosticos\Tests\Unit\PromedioMovilTest has an uninitialized
         property $calculadora. Give it default value or assign it in the
         constructor.
         🪪  property.uninitialized
 ------ ---------------------------------------------------------------------

 [ERROR] Found 2 errors
```

Las pruebas inicializan `$calculadora` en `setUp()`, que PHPUnit ejecuta antes de cada prueba. No es un defecto: PHPStan no sabe que `setUp()` cumple el papel de un constructor. Para decírselo existe `additionalConstructors`:

```neon
    checkUninitializedProperties: true
    additionalConstructors:
        - PHPUnit\Framework\TestCase::setUp
```

Con eso, `composer lint` vuelve a `[OK] No errors`. Es la lección de siempre al agregar una regla: hay que revisar qué reporta en el código existente y decidir, caso por caso, si es un defecto o si la configuración necesita un ajuste.

### Desactivar una regla en una línea concreta

Cuando un hallazgo no se va a corregir, se suprime **solo en esa línea** con `@phpstan-ignore`, el identificador del error y la justificación entre paréntesis. El comentario puede ir al final de la línea o solo, en la línea anterior.

Un caso real: una prueba que pasa a propósito un tipo inválido para comprobar que PHP lo rechaza.

```php
    public function testRechazaUnaVentanaDecimal(): void
    {
        $this->expectException(TypeError::class);

        // La prueba pasa a propósito un tipo inválido: comprueba que strict_types lo rechaza.
        // @phpstan-ignore argument.type (el tipo incorrecto es justamente lo que se prueba)
        (new CalculadoraPronosticos())->promedioMovil([120, 132, 128], 1.5);
    }
```

Sin el comentario `@phpstan-ignore`, PHPStan reporta:

```
  18     Parameter #2 $ventana of method
         Pronosticos\CalculadoraPronosticos::promedioMovil() expects int,
         float given.
         🪪  argument.type
```

Con el comentario, `[OK] No errors`. Reglas para usarlo:

- Siempre con el identificador (`argument.type`). `@phpstan-ignore-line` y `@phpstan-ignore-next-line` no llevan identificador y apagan cualquier error de la línea: evítenlos.
- Siempre con la justificación entre paréntesis.
- Si el error desaparece (porque alguien corrigió el código), PHPStan reporta la supresión que sobra como `ignore.unmatchedIdentifier`, como se vio en la sección 5, y `--fix` la borra. Así las supresiones no se acumulan.

## 7. Integración en el editor y en Git (referencia)

Esta sección es solo de referencia: el proyecto no la necesita para funcionar y la actividad no la exige.

**Editor.** PHPStan no publica una extensión propia para editores. PhpStorm trae la integración incluida (se activa en la configuración de herramientas de calidad de PHP y usa `phpstan.neon`). En Visual Studio Code hay extensiones de la comunidad que ejecutan PHPStan al guardar. Para cualquier editor, el parámetro `editorUrl` hace que los nombres de archivo de la salida sean enlaces que abren el archivo en la línea del error; la documentación muestra, por ejemplo, `phpstorm://open?file=%%file%%&line=%%line%%` y `vscode://file/%%file%%:%%line%%`.

**Hook de Git.** Git puede ejecutar un programa antes de cada commit: el archivo `.git/hooks/pre-commit`. Si el programa termina con un código distinto de 0, el commit se cancela. Un hook mínimo para este proyecto:

```sh
#!/bin/sh
# .git/hooks/pre-commit — se ejecuta antes de cada commit.
composer lint
```

Hay que darle permiso de ejecución (`chmod +x .git/hooks/pre-commit`). La carpeta `.git/hooks` no se sube al repositorio, así que cada persona instala su hook. El hook da retroalimentación antes de subir el cambio, pero no reemplaza al pipeline: se puede saltar con `git commit --no-verify` y el pipeline no.

## 8. Documentación oficial

- PHPStan, primeros pasos: https://phpstan.org/user-guide/getting-started
- Niveles de reglas: https://phpstan.org/user-guide/rule-levels
- Referencia de configuración (`level`, `paths`, `checkUninitializedProperties`, `additionalConstructors`): https://phpstan.org/config-reference
- Línea de comandos y `--fix`: https://phpstan.org/user-guide/command-line-usage
- Ignorar errores (`@phpstan-ignore`): https://phpstan.org/user-guide/ignoring-errors
- Identificadores de errores: https://phpstan.org/error-identifiers
- Formatos de salida y `editorUrl`: https://phpstan.org/user-guide/output-format
- Hooks de Git: https://git-scm.com/docs/githooks
