# Actividad · Pruebas de la Unidad 2 y pipeline de CI/CD (PHP)

**Modalidad:** individual
**Fecha de entrega:** martes 10 de noviembre de 2026, 11:59 p. m.
**Entrega:** enlace a su repositorio en GitHub, con la actividad completa y el pipeline en verde.

## Objetivo

Partir de este proyecto, que hoy solo tiene pruebas unitarias en su pipeline, y llevarlo a un pipeline completo: desarrollar un método nuevo con **TDD**, ampliar las **pruebas unitarias**, escribir escenarios **BDD**, aplicar **análisis estático**, medir la **cobertura** y hacer que el **despliegue (simulado)** solo ocurra cuando todo eso pasa.

El proyecto ya trae un ejemplo funcionando de cada tipo de prueba de la unidad (la tabla final del README dice dónde está cada uno). Al final, en el Paso 8, aplican lo aprendido por su cuenta, a partir de requisitos y sin código de guía.

Antes de empezar, lean el [README.md](README.md): ahí están los conceptos y los comandos que se usan en cada paso.

---

## Paso 0. Tener el proyecto en su propio repositorio

**Por qué:** el pipeline corre en GitHub Actions, y para eso el código tiene que estar en un repositorio suyo.

1. Clonen el proyecto y entren a la carpeta:
   ```bash
   git clone https://github.com/josealfredore2604/pronosticos-php
   cd pronosticos-php
   ```
2. Quiten el remoto del curso, para no intentar subir cambios a él:
   ```bash
   git remote remove origin
   ```
3. Instalen las dependencias. `composer install` instala exactamente las versiones de `composer.lock`, que ya está en el repositorio; así ustedes y el pipeline usan las mismas versiones:
   ```bash
   composer install
   ```
4. En GitHub, creen un repositorio **vacío** (sin README, sin `.gitignore`, sin licencia) con el nombre `pronosticos-php-<su-apellido>`.
5. Conecten su repositorio y suban el código:
   ```bash
   git remote add origin https://github.com/<su-usuario>/pronosticos-php-<su-apellido>.git
   git branch -M main
   git push -u origin main
   ```
6. En GitHub, abran la pestaña **Actions**.

**Qué deben observar:** el workflow **CI/CD** corriendo con tres jobs: *Pruebas unitarias*, *Pruebas BDD* y *Despliegue (simulado)*. Abran el job de despliegue: la última línea dice `Desplegado`.

---

## Paso 1. Conocer el punto de partida

**Por qué:** antes de cambiar algo hay que saber que el punto de partida está en verde. Si después algo se pone en rojo, sabemos que fue por nuestros cambios.

```bash
composer test:unit
composer test:bdd
```

**Qué deben observar:** `OK (9 tests, ...)` en las pruebas unitarias y `3 scenarios (3 passed)` en el ejemplo BDD de promedio móvil.

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

Antes de empezar, lean `docs/EJEMPLO_TDD.md`: muestra el ciclo completo, con salidas reales, aplicado a `mad`.

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

El proyecto ya tiene un ejemplo funcionando: `features/promedio_movil.feature`, su contexto `features/bootstrap/PromedioMovilContext.php` y la configuración `behat.yml`. Úsenlo como modelo; tiene la misma estructura que van a construir aquí, incluido un `Esquema del escenario`.

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

Behat busca los pasos en las **clases de contexto** registradas en `behat.yml`, y carga solo las clases de `features/bootstrap/`. Cada característica tiene su propio contexto: el de promedio móvil ya existe y ustedes crean el de alertas. Creen `features/bootstrap/AlertasContext.php`. Les damos la estructura y el primer paso; completen los demás:

```php
<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use PHPUnit\Framework\Assert;
use Pronosticos\CalculadoraPronosticos;

final class AlertasContext implements Context
{
    private CalculadoraPronosticos $calculadora;
    private float $umbral = 0;
    private bool $alerta = false;

    // Behat crea un AlertasContext nuevo para cada escenario.
    public function __construct()
    {
        $this->calculadora = new CalculadoraPronosticos();
    }

    /**
     * El texto del atributo #[Given] es el patrón del paso. :umbral captura el número
     * de la frase y lo entrega como parámetro: así el paso sirve para cualquier valor.
     */
    #[Given('un umbral de alerta de :umbral %')]
    public function unUmbralDeAlertaDe(float $umbral): void
    {
        $this->umbral = $umbral;
    }

    // Completen: #[When('el error del pronóstico es :error %')]
    // Completen: #[Then('se genera una alerta')]          (usen Assert::assertTrue)
    // Completen: #[Then('no se genera una alerta')]       (usen Assert::assertFalse)
}
```

Para `#[When]` y `#[Then]` agreguen también `use Behat\Step\When;` y `use Behat\Step\Then;`. Behat reconoce el paso por el texto, no por la palabra clave: "Dado" corresponde a `#[Given]`, "Cuando" a `#[When]` y "Entonces" a `#[Then]`.

Registren el contexto nuevo en `behat.yml`, debajo del que ya existe. La indentación es parte de la sintaxis de YAML:

```yaml
default:
  suites:
    default:
      contexts:
        - PromedioMovilContext
        - AlertasContext
```

Si no lo registran, Behat no lo carga y marca los pasos de alertas como *undefined*.

```bash
composer test:bdd
```

**Qué deben observar:** `6 scenarios (6 passed)`: los tres del ejemplo de promedio móvil y los tres de alertas. Si un paso no tiene método, Behat lo marca como *undefined*, les pregunta en qué contexto proponer el código que falta y lo muestra.

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

La guía completa (qué revisa cada nivel, cómo se lee un hallazgo, cómo se corrige y cómo se cambia la configuración) está en `docs/ANALISIS_ESTATICO.md`.

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

### 7.2 Job de cobertura y job de BDD

Siguiendo el mismo modelo, agreguen el job `cobertura` (nombre: "Cobertura"), con dos pasos al final: `composer test:cobertura` y `php bin/verificar-cobertura.php coverage.xml 90`.

Cada job corre en su propia máquina, así que necesita sus tres primeros pasos: descargar el código, instalar PHP e instalar dependencias.

El job `pruebas-bdd` ya existe: el proyecto lo trae para ejecutar el ejemplo de promedio móvil, y corre `composer test:bdd`, que ejecuta todos los escenarios de `features/` con los contextos de `behat.yml`. No lo dupliquen. Lean sus comentarios y, después del push del Paso 7.4, abran su registro en Actions y comprueben que sus tres escenarios de alertas también aparecen ahí.

**Importante:** en el job de cobertura, el paso de instalar PHP debe decir `coverage: pcov` en lugar de `coverage: none`. Sin un driver de cobertura, PHPUnit no puede medir nada.

### 7.3 Que el despliegue dependa de todo

Cambien la línea `needs` del job `despliegue`, que hoy espera a `pruebas-unitarias` y `pruebas-bdd`:

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

**Qué deben observar en Actions:** los cuatro jobs de verificación corren en paralelo, el job *Pruebas BDD* informa `6 scenarios (6 passed)` y, cuando todos pasan, corre el despliegue e imprime `Desplegado`. En el job de cobertura, la línea `Cobertura de líneas: ... %` muestra el porcentaje real.

### 7.5 Comprobar que el pipeline los protege

1. Rompan algo a propósito: en `src/CalculadoraPronosticos.php`, cambien el `>=` de `debeGenerarAlerta` por `>`.
2. Suban el cambio:
   ```bash
   git add .
   git commit -m "prueba: cambio que rompe la regla del umbral"
   git push
   ```
3. En Actions, el pipeline queda en rojo: fallan la prueba unitaria y el escenario BDD que verifican el caso límite (error igual al umbral), y el despliegue **no corre**.
4. Reviertan el cambio y suban de nuevo:
   ```bash
   git revert HEAD --no-edit
   git push
   ```
5. El pipeline vuelve a verde y el despliegue corre.

Tomen captura de las dos ejecuciones (la roja y la verde) para el informe.

---

## Paso 8. Práctica propia

**Por qué:** hasta aquí siguieron instrucciones con el código a la vista. En este paso reciben solo el requisito, como en un proyecto real: ustedes deciden qué probar, cómo nombrarlo y cómo diseñarlo. No hay código de guía.

### 8.1 TDD propio: el error cuadrático medio

Construyan con TDD el método `mse(array $real, array $pronostico): float` en `CalculadoraPronosticos`. No existe en el proyecto.

**Requisito.** El error cuadrático medio (MSE, *mean squared error*) es el promedio de los cuadrados de las diferencias entre la demanda real y el pronóstico. Se expresa en unidades al cuadrado. Como cada diferencia se eleva al cuadrado, un error grande pesa mucho más que varios pequeños: Producción lo usa para detectar pronósticos que fallan mucho en pocos periodos.

> MSE = [(D<sub>1</sub> − F<sub>1</sub>)² + (D<sub>2</sub> − F<sub>2</sub>)² + … + (D<sub>n</sub> − F<sub>n</sub>)²] / n

| Real | Pronóstico | Resultado esperado |
|---|---|---|
| 141, 138, 150 | 126.67, 133.67, 135.67 | 143.15 = (14.33² + 4.33² + 14.33²) / 3 = (205.3489 + 18.7489 + 205.3489) / 3 |

**Reglas de validación**, las mismas de `mad`:

- Si las series no tienen el mismo número de periodos: `InvalidArgumentException` con el mensaje "Las series deben tener el mismo número de periodos".
- Si las series están vacías: `InvalidArgumentException` con el mensaje "Se necesita al menos un periodo".

**Ojo con el redondeo.** Si calculan con los pronósticos sin redondear (380/3, 401/3 y 407/3, que es lo que da el promedio móvil exacto), el MSE da 143.22. La tabla usa los pronósticos redondeados a dos decimales, y con ellos da 143.15. Calcúlenlo a mano antes de escribir la prueba.

Qué deben hacer:

1. Decidan ustedes las pruebas: al menos una para el cálculo, una para cada regla de validación y una más que ustedes consideren necesaria. Escríbanlas **de a una**, viendo cada una en rojo antes de escribir el código.
2. Sigan el ciclo de `docs/EJEMPLO_TDD.md`: rojo, verde con el código mínimo, refactor.
3. Escriban el método en `src/CalculadoraPronosticos.php`, con el bloque `@param list<int|float>` que exige PHPStan.
4. El historial debe mostrar al menos un commit de rojo, uno de verde y uno de refactor, en ese orden. Por ejemplo:
   ```bash
   git commit -m "test: prueba en rojo para mse"
   git commit -m "feat: implementa mse"
   git commit -m "refactor: mejora la legibilidad de mse"
   ```
   Compruébenlo con `git log --oneline`.

### 8.2 Pruebas unitarias diseñadas por ustedes

Propongan **al menos tres casos nuevos** para `promedioMovil` y `mad` (entre las dos) que no estén en las pruebas existentes ni en los ejemplos de `docs/`. Para cada caso, antes de escribirlo, anoten en el informe:

| Función | Entrada | Resultado esperado (calculado a mano) | Qué riesgo cubre |
|---|---|---|---|

"Qué riesgo cubre" significa qué defecto encontraría la prueba si alguien lo introdujera mañana. Algunas preguntas para pensar (no son la respuesta): ¿qué pasa con decimales? ¿Con una serie de un solo periodo? ¿Importan los valores que quedan fuera de la ventana? ¿Y el signo de la diferencia en `mad`?

Si un caso falla, **no cambien la prueba para que pase**: anoten en el informe qué encontraron y qué debería decidir Producción.

```bash
composer test:unit
git add .
git commit -m "test: casos nuevos para promedioMovil y mad"
```

### 8.3 BDD propio

Lean `src/CalculadoraPronosticos.php` y elijan una regla del negocio que todavía no tenga escenario, por ejemplo cómo se calcula el MAPE cuando un periodo tiene demanda 0. Escriban **al menos un escenario** para esa regla, redactado para que Producción lo pueda leer: sin nombres de funciones ni de variables, con los datos y el resultado en palabras del negocio.

Creen un archivo `features/<nombre>.feature` y su propio contexto en `features/bootstrap/<Nombre>Context.php`, registrado en `behat.yml` junto a los demás. Behat comparte los pasos de todos los contextos de la suite: usen frases propias, porque definir dos veces el mismo paso es un error (*redundant*) y cada contexto guarda su propio estado.

```bash
composer test:bdd
git add .
git commit -m "test: escenario BDD de una regla propia"
git push
```

Comprueben en Actions que su escenario también corre en el job *Pruebas BDD*.

### 8.4 Análisis estático configurado por ustedes

Sigan la sección 6 de `docs/ANALISIS_ESTATICO.md`:

1. Agreguen o ajusten **al menos una regla** en `phpstan.neon`, distinta de los ejemplos del documento (el nivel 8 o `checkUninitializedProperties` (pueden usar otro nivel u otro parámetro de la referencia de configuración de PHPStan)).
2. Provoquen a propósito un hallazgo de esa regla y guarden la salida.
3. Corríjanlo. Si deciden no corregirlo, suprímanlo solo en esa línea y con su justificación.
4. Revisen qué reporta la regla en el código que ya existía y decidan qué hacer.
5. Dejen `composer lint` sin hallazgos y el pipeline en verde.

```bash
git add .
git commit -m "chore: ajusta la configuración del análisis estático"
git push
```

### 8.5 Revisión estática con checklist

Elijan un método de `src/CalculadoraPronosticos.php` (`promedioMovil`, `mape`, `debeGenerarAlerta` o `validarSeries`; no `mad`, que ya está analizado en `docs/EJEMPLO_TDD.md`, ni los que escribieron ustedes) y revísenlo con este checklist. Es una revisión informal e individual (ver "Revisiones estáticas" en el README): lean el código sin ejecutarlo.

| # | Pregunta |
|---|---|
| 1 | ¿El nombre dice qué hace, sin tener que leer el cuerpo? |
| 2 | ¿Los nombres de las variables y los parámetros dicen qué contienen? |
| 3 | ¿Tiene una sola responsabilidad (calcula, o valida, o presenta, pero no varias)? |
| 4 | ¿Repite lógica que ya existe en otro lugar? |
| 5 | ¿Hay números escritos directamente que deberían tener nombre? |
| 6 | ¿Los comentarios explican el porqué y no repiten lo que el código ya dice? |
| 7 | ¿Cada dato inválido produce un error explícito, con un mensaje que dice qué está mal? |
| 8 | ¿Se entiende de una lectura? ¿Cuántas decisiones tiene? |
| 9 | ¿Cada comportamiento tiene al menos una prueba? ¿Cuál falta? |

En el informe copien la tabla con su respuesta (sí o no) y la evidencia de cada pregunta (línea del código y explicación). Anoten **al menos dos hallazgos**: qué encontraron, dónde, por qué es un problema y qué propondrían. Si no encuentran ninguno, expliquen con el checklist por qué el método ya cumple. No hace falta cambiar el código; si deciden hacerlo, primero una prueba que lo proteja.

---

## Paso 9. Informe

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
   - Paso 8.1: ¿qué prueba escribieron primero y por qué? ¿Por qué el MSE de la tabla es 143.15 y no 143.22, y cuál de los dos debería usar Producción?
   - Paso 8.2: la tabla de sus tres casos nuevos, con el riesgo que cubre cada uno.
   - Paso 8.3: ¿qué regla eligieron, dónde está en el código y por qué su escenario es una prueba funcional y no una unitaria?
   - Paso 8.4: ¿qué regla agregaron o ajustaron, qué hallazgo provocaron, qué encontró en el código existente y qué decidieron?
   - Paso 8.5: el checklist completo y sus hallazgos. ¿Qué encontraron ustedes que una herramienta de análisis estático no habría encontrado?
   - Si este proyecto fuera una historia de usuario en un sprint, ¿qué pondrían en su *Definition of Done* y en qué momento debería participar quien prueba?

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
- [ ] `features/alertas.feature` tiene `# language: es` en la primera línea y tres escenarios, `AlertasContext` implementa sus pasos y está registrado en `behat.yml`.
- [ ] Los escenarios de alertas corren en el job *Pruebas BDD* que ya trae el pipeline.
- [ ] `mse` existe, cumple el requisito del Paso 8.1 y el historial muestra los commits de rojo, verde y refactor.
- [ ] Hay al menos tres casos unitarios nuevos para promedio móvil y `mad`, justificados en el informe.
- [ ] Hay al menos un `.feature` propio con un escenario nuevo, y corre en el job *Pruebas BDD*.
- [ ] La configuración del análisis estático tiene al menos una regla agregada o ajustada, explicada en el informe.
- [ ] El informe tiene el checklist de la revisión estática con al menos dos hallazgos o la justificación de por qué no hay.
- [ ] `composer lint` no reporta nada.
- [ ] La cobertura de líneas es de 90 % o más.
- [ ] El workflow tiene los jobs de análisis estático, pruebas unitarias, BDD, cobertura y despliegue, y el despliegue depende de los cuatro.
- [ ] El informe tiene las capturas, la salida de cobertura y las respuestas.

## Rúbrica

| Criterio | Peso |
|---|---|
| TDD: método correcto y commits de rojo, verde y refactor | 15 % |
| Pruebas unitarias: casos completos, nombres claros, tolerancia numérica | 10 % |
| BDD: escenarios de alertas correctos en español, contexto propio registrado en `behat.yml` | 10 % |
| Análisis estático y cobertura | 10 % |
| Pipeline: job de cobertura con driver, escenarios de alertas en el job de BDD, `needs` del despliegue y evidencia del rojo y el verde | 15 % |
| TDD propio: `mse` correcto, pruebas decididas por ustedes y commits de rojo, verde y refactor | 10 % |
| Pruebas unitarias diseñadas por ustedes: casos nuevos y riesgo justificado | 5 % |
| BDD propio: escenario legible para Producción y pasos que lo ejecutan | 5 % |
| Análisis estático configurado por ustedes: regla, hallazgo y decisión explicada | 5 % |
| Revisión estática: checklist completo y hallazgos razonados | 5 % |
| Informe: respuestas razonadas con sus palabras | 10 % |
| **Total** | **100 %** |

## Documentación oficial

- PHPUnit, aserciones: https://docs.phpunit.de/
- Behat, definición de pasos: https://docs.behat.org/en/latest/user_guide/context/definitions.html
- Behat, configuración en YAML (suites y contextos): https://docs.behat.org/en/v3.x/user_guide/configuration/yaml_configuration.html
- PHPStan, niveles: https://phpstan.org/user-guide/rule-levels
- Composer, scripts: https://getcomposer.org/doc/articles/scripts.md
- Acción setup-php: https://github.com/shivammathur/setup-php
- Sintaxis de workflows: https://docs.github.com/actions/reference/workflow-syntax-for-github-actions
