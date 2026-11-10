# Actividad · Pruebas de la Unidad 2 y pipeline de CI/CD (PHP)

**Modalidad:** individual
**Fecha de entrega:** [FECHA DE ENTREGA]
**Entrega:** enlace a su repositorio en GitHub, con la actividad completa y el pipeline en verde.

## Objetivo

Partir de este proyecto, que hoy solo tiene pruebas unitarias en su pipeline, y llevarlo a un pipeline completo: desarrollar un método nuevo con **TDD**, ampliar las **pruebas unitarias**, escribir escenarios **BDD**, aplicar **análisis estático**, medir la **cobertura** y hacer que el **despliegue (simulado)** solo ocurra cuando todo eso pasa.

Antes de empezar, lean el [README.md](README.md): ahí están los conceptos y los comandos que se usan en cada paso.

---

## Paso 0. Tener el proyecto en su propio repositorio

**Por qué:** el pipeline corre en GitHub Actions, y para eso el código tiene que estar en un repositorio suyo.

1. Clonen el proyecto y entren a la carpeta:
   ```bash
   git clone <url-del-repositorio-del-curso>
   cd pronosticos-php
   ```
2. Quiten el remoto del curso, para no intentar subir cambios a él:
   ```bash
   git remote remove origin
   ```
3. Instalen las dependencias. Este comando crea `composer.lock`, el archivo con las versiones exactas que se instalaron; súbanlo al repositorio para que el pipeline use esas mismas versiones:
   ```bash
   composer install
   git add composer.lock
   git commit -m "build: fija las versiones de las dependencias"
   ```
4. En GitHub, creen un repositorio **vacío** (sin README, sin `.gitignore`, sin licencia) con el nombre `pronosticos-php-<su-apellido>`.
5. Conecten su repositorio y suban el código:
   ```bash
   git remote add origin https://github.com/<su-usuario>/pronosticos-php-<su-apellido>.git
   git branch -M main
   git push -u origin main
   ```
6. En GitHub, abran la pestaña **Actions**.

**Qué deben observar:** el workflow **CI/CD** corriendo con dos jobs: *Pruebas unitarias* y *Despliegue (simulado)*. Abran el job de despliegue: la última línea dice `Desplegado`.

---

## Paso 1. Conocer el punto de partida

**Por qué:** antes de cambiar algo hay que saber que el punto de partida está en verde. Si después algo se pone en rojo, sabemos que fue por nuestros cambios.

```bash
composer test:unit
```

**Qué deben observar:** `OK (9 tests, ...)`.

Abran `src/CalculadoraPronosticos.php` y `tests/Unit/MadTest.php`. Fíjense en tres cosas que deben repetir en sus pruebas: cada prueba verifica un solo comportamiento, los nombres de los métodos dicen qué se espera, y los decimales se comparan con `assertEqualsWithDelta`.

---

## Paso 2. Desarrollar un método nuevo con TDD

Van a construir `suavizacionExponencial(array $serie, float $alfa): float`, que pronostica el siguiente periodo con suavización exponencial simple:

> F<sub>t+1</sub> = F<sub>t</sub> + α · (D<sub>t</sub> − F<sub>t</sub>), empezando con F<sub>2</sub> = D<sub>1</sub>

Donde F es el pronóstico, D la demanda real y α (alfa) un número entre 0 y 1.

| Serie | Alfa | Resultado esperado |
|---|---|---|
| 120, 132, 128, 141, 138, 150 | 0.3 | 137.5546 |
| 120, 132, 128, 141, 138, 150 | 0 | 120.00 (con alfa 0 el pronóstico nunca cambia) |
| 120, 132, 128, 141, 138, 150 | 1 | 150.00 (con alfa 1 el pronóstico es la última demanda) |
| cualquiera | −0.1 o 1.5 | `InvalidArgumentException` con el mensaje "Alfa debe estar entre 0 y 1" |

**Por qué:** en TDD la prueba se escribe primero. Si escriben el código primero, la prueba solo confirma lo que ya hicieron; si la escriben antes, la prueba define qué debe hacer el código.

### 2.1 Rojo

Creen `tests/Unit/SuavizacionExponencialTest.php` con **solo** esta prueba:

```php
<?php

declare(strict_types=1);

namespace Pronosticos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pronosticos\CalculadoraPronosticos;

final class SuavizacionExponencialTest extends TestCase
{
    private const SERIE = [120, 132, 128, 141, 138, 150];

    public function testPronosticaElSiguientePeriodoConAlfa03(): void
    {
        $resultado = (new CalculadoraPronosticos())->suavizacionExponencial(self::SERIE, 0.3);

        $this->assertEqualsWithDelta(137.5546, $resultado, 0.01);
    }
}
```

```bash
composer test:unit
```

**Qué deben observar:** un error `Call to undefined method Pronosticos\CalculadoraPronosticos::suavizacionExponencial()`. Ese es el rojo: la prueba existe y el método todavía no.

```bash
git add .
git commit -m "test: prueba en rojo para suavizacionExponencial"
```

### 2.2 Verde

Escriban en `src/CalculadoraPronosticos.php` el código **mínimo** para que esa prueba pase. Incluyan el bloque `@param list<int|float> $serie` sobre el método, igual que en los demás: PHPStan lo va a exigir en el Paso 5.

```bash
composer test:unit
git add .
git commit -m "feat: implementa suavizacionExponencial"
```

### 2.3 Más casos y refactor

Agreguen, una por una, pruebas para las filas restantes de la tabla (alfa 0, alfa 1 y alfa fuera de rango, con `expectException`). Con cada prueba nueva repitan el ciclo: primero la ven fallar, después ajustan el código.

Cuando todas pasen, mejoren el código sin cambiar su comportamiento: nombres claros, un comentario que explique la fórmula, la validación de alfa al inicio. Después de cada cambio corran `composer test:unit`; nunca deben quedar en rojo.

```bash
git add .
git commit -m "refactor: mejora la legibilidad de suavizacionExponencial"
```

---

## Paso 3. Ampliar las pruebas unitarias

**Por qué:** `mape` y `debeGenerarAlerta` ya existen pero no tienen pruebas. Código sin pruebas puede romperse en cualquier cambio sin que nadie lo note.

Creen `tests/Unit/MapeTest.php` y `tests/Unit/AlertasTest.php` con al menos estos casos:

| Método | Entrada | Esperado | Qué comprueba |
|---|---|---|---|
| `mape` | real `[141, 138, 150]`, pronóstico `[126.67, 133.67, 135.67]` | 7.62 | El cálculo normal |
| `mape` | real `[80, 0, 95]`, pronóstico `[80, 0, 100]` | 2.63 | El periodo con demanda 0 se excluye |
| `mape` | real `[0, 0]`, pronóstico `[10, 20]` | `InvalidArgumentException` | Sin periodos válidos el MAPE no existe |
| `debeGenerarAlerta` | error 12, umbral 10 | `true` | Supera el umbral |
| `debeGenerarAlerta` | error 10, umbral 10 | `true` | Caso límite: igual al umbral sí alerta |
| `debeGenerarAlerta` | error 7, umbral 10 | `false` | No alcanza el umbral |
| `debeGenerarAlerta` | umbral 150 | `InvalidArgumentException` | Umbral fuera de rango |

Calculen a mano el 2.63 antes de escribir la prueba: ¿por qué el promedio se divide entre 2 y no entre 3?

```bash
composer test:unit
git add .
git commit -m "test: pruebas unitarias de mape y debeGenerarAlerta"
```

---

## Paso 4. Escribir escenarios BDD con Behat

**Por qué:** la regla "el error igual al umbral sí alerta" es una decisión de negocio. En BDD la escribimos de forma que Producción pueda leerla y confirmarla, y esa misma descripción se ejecuta como prueba.

### 4.1 El escenario

Creen `features/alertas.feature`. La línea `# language: es` tiene que ser la **primera** del archivo:

```gherkin
# language: es
Característica: Alertas de desviación del pronóstico
  Como planeador de producción
  Quiero recibir una alerta cuando el error del pronóstico sea alto
  Para revisar el plan de producción a tiempo

  Escenario: El error supera el umbral
    Dado un umbral de alerta de 10 %
    Cuando el error del pronóstico es 12 %
    Entonces se genera una alerta
```

Agreguen ustedes dos escenarios más: el error **igual** al umbral (10 %) y el error **por debajo** del umbral (7 %).

### 4.2 Los pasos

Behat busca los pasos en la clase `FeatureContext`, dentro de `features/bootstrap/`. Creen `features/bootstrap/FeatureContext.php`. Les damos la estructura y el primer paso; completen los demás:

```php
<?php

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use Pronosticos\CalculadoraPronosticos;

final class FeatureContext implements Context
{
    private CalculadoraPronosticos $calculadora;
    private float $umbral = 0;
    private bool $alerta = false;

    // Behat crea un FeatureContext nuevo para cada escenario.
    public function __construct()
    {
        $this->calculadora = new CalculadoraPronosticos();
    }

    /**
     * El texto después de @Given es el patrón del paso. :umbral captura el número
     * de la frase y lo entrega como parámetro: así el paso sirve para cualquier valor.
     *
     * @Given un umbral de alerta de :umbral %
     */
    public function unUmbralDeAlertaDe(float $umbral): void
    {
        $this->umbral = $umbral;
    }

    // Completen: @When el error del pronóstico es :error %
    // Completen: @Then se genera una alerta          (usen Assert::assertTrue)
    // Completen: @Then no se genera una alerta       (usen Assert::assertFalse)
}
```

Behat reconoce el paso por el texto, no por la palabra clave: "Dado" corresponde a `@Given`, "Cuando" a `@When` y "Entonces" a `@Then`.

```bash
composer test:bdd
```

**Qué deben observar:** `3 scenarios (3 passed)`. Si un paso no tiene método, Behat lo marca como *undefined* y les propone el código del método que falta.

```bash
git add .
git commit -m "test: escenarios BDD de alertas de desviación"
```

---

## Paso 5. Análisis estático con PHPStan

**Por qué:** el análisis estático encuentra problemas sin ejecutar el código, incluso en líneas que ninguna prueba recorre.

```bash
composer lint
```

Si aparecen hallazgos, corríjanlos. Los más comunes en el nivel 6 son un parámetro sin tipo o un arreglo sin el bloque `@param list<...>`.

Ahora provoquen un hallazgo a propósito para ver cómo se reporta. En cualquier método de `CalculadoraPronosticos` agreguen la línea `return $this->metodoQueNoExiste();` antes del `return` real y corran `composer lint`. Lean el mensaje: archivo, línea y descripción. Después borren la línea.

```bash
git add .
git commit -m "style: código sin hallazgos de PHPStan"
```

---

## Paso 6. Cobertura de código

**Por qué:** la cobertura muestra qué partes del código ninguna prueba ejecuta.

Para medirla en su equipo necesitan PCOV o Xdebug (ver el README). Si no lo tienen, pueden hacer este paso directamente en el pipeline del Paso 7.

```bash
composer test:cobertura
php bin/verificar-cobertura.php coverage.xml 90
```

**Qué deben observar:** el primer comando muestra el porcentaje de clases, métodos y líneas, y genera `coverage.xml`. El segundo dice si se alcanza el 90 %: si no, termina con error. Si están por debajo, escriban las pruebas que faltan (pista: revisen las validaciones que lanzan `InvalidArgumentException`).

---

## Paso 7. Llevar todo al pipeline

**Por qué:** hasta ahora todo depende de que alguien corra los comandos. En el pipeline se ejecutan solos en cada push, y el despliegue solo ocurre si todo pasa.

Abran `.github/workflows/ci-cd.yml`.

### 7.1 Job de análisis estático

Agreguen este job debajo de `jobs:`, al mismo nivel que `pruebas-unitarias`:

```yaml
  analisis-estatico:
    name: Análisis estático
    runs-on: ubuntu-latest
    steps:
      - name: Descargar el código
        uses: actions/checkout@v6

      - name: Instalar PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Instalar dependencias
        run: composer install --no-interaction --no-progress

      # Revisa tipos, métodos inexistentes y código inalcanzable sin ejecutar el código.
      - name: Revisar el código con PHPStan
        run: composer lint
```

En YAML la indentación es parte de la sintaxis: el job tiene que quedar con dos espacios, igual que `pruebas-unitarias`.

### 7.2 Jobs de BDD y de cobertura

Siguiendo el mismo modelo, agreguen ustedes dos jobs más:

- `pruebas-bdd` (nombre: "Pruebas BDD"), que corra `composer test:bdd`.
- `cobertura` (nombre: "Cobertura"), con dos pasos al final: `composer test:cobertura` y `php bin/verificar-cobertura.php coverage.xml 90`.

Cada job corre en su propia máquina, así que cada uno necesita sus tres primeros pasos: descargar el código, instalar PHP e instalar dependencias.

**Importante:** en el job de cobertura, el paso de instalar PHP debe decir `coverage: pcov` en lugar de `coverage: none`. Sin un driver de cobertura, PHPUnit no puede medir nada.

### 7.3 Que el despliegue dependa de todo

Cambien la línea `needs` del job `despliegue`:

```yaml
    needs: [analisis-estatico, pruebas-unitarias, pruebas-bdd, cobertura]
```

Ahora el despliegue espera a los cuatro jobs y solo corre si los cuatro terminan bien.

### 7.4 Subir y comprobar

```bash
git add .
git commit -m "ci: análisis estático, BDD y cobertura en el pipeline"
git push
```

**Qué deben observar en Actions:** los cuatro jobs de verificación corren en paralelo y, cuando todos pasan, corre el despliegue e imprime `Desplegado`. En el job de cobertura, la línea `Cobertura de líneas: ... %` muestra el porcentaje real.

### 7.5 Comprobar que el pipeline los protege

1. Rompan algo a propósito: en `src/CalculadoraPronosticos.php`, cambien el `>=` de `debeGenerarAlerta` por `>`.
2. Suban el cambio:
   ```bash
   git add .
   git commit -m "prueba: cambio que rompe la regla del umbral"
   git push
   ```
3. En Actions, el pipeline queda en rojo: fallan las pruebas que verifican el caso límite y el despliegue **no corre**.
4. Reviertan el cambio y suban de nuevo:
   ```bash
   git revert HEAD --no-edit
   git push
   ```
5. El pipeline vuelve a verde y el despliegue corre.

Tomen captura de las dos ejecuciones (la roja y la verde) para el informe.

---

## Paso 8. Informe

Creen en la raíz del repositorio el archivo `INFORME_<Nombre>_<Apellido>.md` con:

1. El enlace a su repositorio.
2. Las capturas del pipeline en rojo (Paso 7.5) y en verde.
3. La salida del job de cobertura del pipeline (o de `composer test:cobertura` si lo corrieron en su equipo).
4. Respuestas breves, con sus palabras:
   - ¿Qué vieron en el rojo del Paso 2.1 y por qué es importante verlo antes de escribir el código?
   - ¿Por qué el MAPE del caso `[80, 0, 95]` se divide entre 2?
   - ¿Qué diferencia hay entre una prueba unitaria y un escenario BDD en este proyecto?
   - ¿Por qué el proyecto necesita `bin/verificar-cobertura.php` para que el pipeline falle con cobertura baja?
   - En el Paso 7.5, ¿qué pruebas fallaron y por qué el despliegue no corrió?

```bash
git add .
git commit -m "docs: informe de la actividad"
git push
```

---

## Checklist de entrega

- [ ] El repositorio es suyo, incluye `composer.lock` y el último pipeline está en verde.
- [ ] `suavizacionExponencial` existe y el historial muestra los commits de rojo, verde y refactor.
- [ ] Hay pruebas unitarias de `suavizacionExponencial`, `mape` y `debeGenerarAlerta`, con los casos de las tablas.
- [ ] `features/alertas.feature` tiene `# language: es` en la primera línea y tres escenarios, y `FeatureContext` implementa sus pasos.
- [ ] `composer lint` no reporta nada.
- [ ] La cobertura de líneas es de 90 % o más.
- [ ] El workflow tiene los jobs de análisis estático, pruebas unitarias, BDD, cobertura y despliegue, y el despliegue depende de los cuatro.
- [ ] El informe tiene las capturas, la salida de cobertura y las respuestas.

## Rúbrica

| Criterio | Peso |
|---|---|
| TDD: método correcto y commits de rojo, verde y refactor | 20 % |
| Pruebas unitarias: casos completos, nombres claros, tolerancia numérica | 20 % |
| BDD: escenarios correctos en español y pasos que los ejecutan | 15 % |
| Análisis estático y cobertura | 15 % |
| Pipeline: jobs correctos, driver de cobertura, `needs` del despliegue y evidencia del rojo y el verde | 20 % |
| Informe: respuestas razonadas con sus palabras | 10 % |

## Documentación oficial

- PHPUnit, aserciones: https://docs.phpunit.de/
- Behat, definición de pasos: https://docs.behat.org/en/latest/user_guide/context/definitions.html
- PHPStan, niveles: https://phpstan.org/user-guide/rule-levels
- Composer, scripts: https://getcomposer.org/doc/articles/scripts.md
- Acción setup-php: https://github.com/shivammathur/setup-php
- Sintaxis de workflows: https://docs.github.com/actions/reference/workflow-syntax-for-github-actions
