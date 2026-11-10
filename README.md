# Pronósticos de demanda · PHP con Composer

Proyecto base del curso de Pruebas de Software. Calcula pronósticos de demanda y mide qué tan lejos quedaron de la demanda real, con las mismas reglas del proyecto nuclear "Sistema web para la predicción de la demanda en producción industrial". Incluye una API HTTP mínima que corre con el servidor incorporado de PHP.

No usa bases de datos, contenedores, frameworks ni servidores web externos: solo PHP y Composer.

La actividad que deben desarrollar está en [ACTIVIDAD.md](ACTIVIDAD.md) (en GitHub: https://github.com/josealfredore2604/pronosticos-php/blob/main/ACTIVIDAD.md).

---

## Qué hace

| Método | Qué calcula | Ejemplo con la serie 120, 132, 128, 141, 138, 150 |
|---|---|---|
| `promedioMovil($serie, $ventana)` | Pronóstico del siguiente periodo con el promedio de los últimos `$ventana` valores | Ventana 3: (141 + 138 + 150) / 3 = 143,00 |
| `mad($real, $pronostico)` | Error absoluto medio, en unidades | 11,00 para los periodos 4 a 6 |
| `mape($real, $pronostico)` | Error porcentual absoluto medio; excluye los periodos con demanda 0 | 7,62 % para los periodos 4 a 6 |
| `debeGenerarAlerta($error, $umbral)` | Si un error debe disparar una alerta; alerta cuando el error es mayor **o igual** al umbral | Error 10 % con umbral 10 %: sí alerta |

Las reglas están en `src/CalculadoraPronosticos.php`. La API está en `public/index.php`.

| Método y ruta | Qué hace |
|---|---|
| `GET /api/salud` | Responde `{"estado":"ok"}` si la aplicación está arriba |
| `POST /api/pronosticos/mad` | Recibe `{"real": [...], "pronostico": [...]}` y responde el MAD |

## Requisitos

- [PHP](https://www.php.net/downloads) 8.3 o superior, con las extensiones `dom`, `mbstring` y `xml` (vienen en la mayoría de instalaciones).
- [Composer](https://getcomposer.org/download/).

```bash
php --version
composer --version
```

Para ver la cobertura **en su equipo** necesitan además un driver de cobertura: [PCOV](https://github.com/krakjoe/pcov) o [Xdebug](https://xdebug.org/docs/install). No es obligatorio: el pipeline de GitHub Actions trae el suyo y ahí la cobertura siempre se puede ver.

## Cómo levantarlo

```bash
git clone https://github.com/josealfredore2604/pronosticos-php
cd pronosticos-php

composer install
composer start
```

`composer install` descarga las dependencias en `vendor/`, con las versiones exactas que fija `composer.lock`. `composer start` levanta la API en http://localhost:8000 con el servidor incorporado de PHP.

En otra terminal:

```bash
curl http://localhost:8000/api/salud

curl -X POST http://localhost:8000/api/pronosticos/mad \
  -H "Content-Type: application/json" \
  -d '{"real": [141, 138, 150], "pronostico": [126.67, 133.67, 135.67]}'
```

La segunda petición responde un MAD de aproximadamente 11.00. Para detener la API, `Ctrl + C`.

El servidor incorporado de PHP sirve para desarrollo, no para producción.

## Cómo correr las pruebas

```bash
composer test:unit
```

Al iniciar el proyecto hay 9 pruebas unitarias y 3 escenarios BDD (el ejemplo de promedio móvil), y todos pasan.

Comandos que van a usar en la actividad (todos están en la sección `scripts` de `composer.json`):

| Comando | Qué hace |
|---|---|
| `composer test:unit` | Pruebas unitarias con PHPUnit |
| `composer test:bdd` | Escenarios BDD con Behat: el ejemplo de promedio móvil y los escenarios que ustedes agreguen |
| `composer lint` | Análisis estático con PHPStan |
| `composer test:cobertura` | Pruebas con reporte de cobertura (necesita PCOV o Xdebug) |
| `php bin/verificar-cobertura.php coverage.xml 90` | Falla si la cobertura de líneas está por debajo de 90 % |

## Estructura

```
pronosticos-php/
├── .github/workflows/ci-cd.yml      Pipeline de CI/CD de GitHub Actions
├── src/
│   └── CalculadoraPronosticos.php   Reglas de negocio
├── public/
│   └── index.php                    API HTTP
├── tests/
│   └── Unit/                        Pruebas unitarias
├── features/
│   ├── promedio_movil.feature       Escenarios BDD del ejemplo de promedio móvil
│   └── bootstrap/
│       └── PromedioMovilContext.php Pasos del ejemplo de promedio móvil
├── docs/
│   ├── EJEMPLO_TDD.md               Ciclo rojo-verde-refactor de mad, con salidas reales
│   └── ANALISIS_ESTATICO.md         Guía de PHPStan: niveles, ejecución y configuración
├── bin/
│   └── verificar-cobertura.php      Verifica el mínimo de cobertura
├── composer.json                    Dependencias, autoload y scripts
├── phpunit.xml                      Configuración de PHPUnit
├── phpstan.neon                     Configuración de PHPStan
├── behat.yml                        Configuración de Behat: suites y contextos
├── README.md
└── ACTIVIDAD.md
```

## Herramientas

| Herramienta | Para qué | Dónde se configura |
|---|---|---|
| [Composer](https://getcomposer.org/doc/) | Dependencias, autoload de clases y scripts | `composer.json` |
| [PHPUnit](https://docs.phpunit.de/) | Ejecuta las pruebas unitarias y mide la cobertura | `phpunit.xml` |
| [Behat](https://docs.behat.org/) | Ejecuta escenarios escritos en Gherkin | `behat.yml` y `features/` |
| [PHPStan](https://phpstan.org/user-guide/getting-started) | Analizador estático | `phpstan.neon` |

Todas están en `require-dev`: se usan para construir y verificar el software, no para ejecutarlo.

### Autoload

La sección `autoload` de `composer.json` (estándar PSR-4) indica que las clases del espacio de nombres `Pronosticos\` están en `src/`. Así nunca hay que escribir `require` para cada clase: basta con `use Pronosticos\CalculadoraPronosticos;`.

---

## Conceptos

### Prueba unitaria

Verifica una unidad pequeña de código (un método) de forma aislada, sin base de datos, red ni archivos. Por eso es rápida y su resultado no depende del entorno. Las pruebas de este proyecto siguen el patrón **Arrange-Act-Assert**: preparar los datos, ejecutar el método y verificar el resultado, con un solo comportamiento por prueba.

En PHPUnit cada clase de prueba extiende `TestCase`, cada método que empieza por `test` es una prueba, y `setUp()` se ejecuta antes de cada una para que todas arranquen desde el mismo estado.

Una buena prueba unitaria cumple **FIRST**: rápida (*Fast*), independiente de las demás (*Independent*), repetible en cualquier máquina (*Repeatable*), que decide sola si pasa o falla (*Self-validating*) y escrita a tiempo, junto con el código (*Timely*).

### Tolerancia numérica

Los cálculos con decimales no dan resultados exactos: `0.1 + 0.2` no es exactamente `0.3`. Por eso los resultados numéricos se comparan con tolerancia:

```php
$this->assertEqualsWithDelta(11.00, $resultado, 0.01);
```

El tercer argumento es la diferencia máxima aceptada. El valor esperado siempre sale de un cálculo independiente, nunca del mismo código que se está probando.

### TDD (desarrollo guiado por pruebas)

La prueba se escribe antes que el código, en ciclos cortos:

1. **Rojo**: se escribe una prueba para un comportamiento que todavía no existe. Falla.
2. **Verde**: se escribe el código mínimo para que pase.
3. **Refactor**: se mejora el código sin cambiar su comportamiento. Las pruebas siguen en verde.

El ciclo completo, reconstruido paso a paso con `mad` y con las salidas reales de la consola, está en [docs/EJEMPLO_TDD.md](docs/EJEMPLO_TDD.md).

### BDD (desarrollo guiado por comportamiento)

El comportamiento esperado se describe en lenguaje natural con el formato **Dado / Cuando / Entonces** (Gherkin), antes de programarlo. Una persona del negocio puede leer el escenario y confirmar si es lo que necesita. En Behat, cada línea del escenario se conecta con un método de una **clase de contexto**. Behat solo usa los contextos registrados en `behat.yml`, y cada característica del sistema tiene el suyo: el ejemplo usa `PromedioMovilContext`.

La primera línea del archivo `.feature` debe ser `# language: es` para usar las palabras clave en español. Si esa línea no está de primera, el archivo se lee en inglés y "Característica", "Dado" o "Cuando" no se reconocen.

El proyecto trae un ejemplo funcionando: `features/promedio_movil.feature` describe el pronóstico con promedio móvil y `features/bootstrap/PromedioMovilContext.php` conecta cada frase con el código. Tiene tres casos: el cálculo con la serie 120, 132, 128, 141, 138, 150 y ventana 3 (resultado 143), una ventana mayor que la serie y una ventana de 0, estos dos con error. Se ejecuta con `composer test:bdd`.

Los dos casos de error están escritos como un **Esquema del escenario**: un solo escenario con marcadores (`<ventana>`, `<mensaje>`) y una tabla de **Ejemplos**. Gherkin lo ejecuta una vez por cada fila de la tabla, con los valores de esa fila. Sirve cuando varios casos siguen los mismos pasos y solo cambian los datos. Behat muestra el esquema con su tabla y cuenta cada fila como un escenario; por eso `composer test:bdd` informa `3 scenarios (3 passed)`.

### Pruebas funcionales

Una prueba funcional verifica **qué** hace el sistema: compara su comportamiento con el requisito, sin mirar cómo está programado. Es una prueba de **caja negra**. Quien la diseña parte del requisito ("con la serie 120, 132, 128, 141, 138, 150 y una ventana de 3 periodos, el pronóstico del periodo 7 es 143"), elige las entradas y los resultados esperados, y no necesita leer el código.

Los escenarios BDD de este proyecto son pruebas funcionales: cada escenario es un requisito de Producción, escrito en Gherkin y ejecutado contra el sistema. El ejemplo está en `features/promedio_movil.feature`.

| | Prueba unitaria | Prueba funcional (escenario BDD) |
|---|---|---|
| Pregunta que responde | ¿Esta función hace bien su trabajo? | ¿El sistema cumple el requisito? |
| Se diseña a partir de | El código: quien la escribe conoce la función, sus ramas y sus validaciones | El requisito: no hace falta mirar el código |
| Quién la puede leer | El equipo de desarrollo | Desarrollo, pruebas y Producción |
| Lenguaje | PHP | Gherkin, en español |
| Si falla, indica | Qué función y qué línea fallaron | Qué regla del negocio dejó de cumplirse |

Las dos pueden verificar la misma regla. En `tests/Unit/PromedioMovilTest.php`, `testConVentanaDeUnPeriodoRepiteElUltimoValor` comprueba un caso límite que sale de conocer cómo está escrito el método (el corte con `array_slice` y ventana 1). El escenario de promedio móvil, en cambio, verifica la regla tal como la dijo Producción y seguiría siendo válido aunque `promedioMovil` se reescribiera por completo.

En este proyecto los escenarios llaman directamente a las funciones de cálculo. Verificar la misma regla a través de la API HTTP (`POST /api/pronosticos/mad`) también es una prueba funcional, pero además comprueba que las piezas del sistema funcionan juntas: es una **prueba de integración**, y las pruebas de integración se ven en la Unidad 3. Por eso este proyecto no incluye pruebas de la API HTTP.

### Análisis estático

Revisa el código sin ejecutarlo. PHPStan encuentra métodos que no existen, tipos incompatibles, variables sin definir y código que nunca se ejecuta. Trabaja por niveles, de 0 a 10; este proyecto usa el nivel 6, que además exige tipos en parámetros y retornos y que los arreglos documenten qué contienen (`@param list<int|float> $serie`).

Qué reglas están activas, qué detecta cada una, cómo se lee un hallazgo y cómo se cambia la configuración: [docs/ANALISIS_ESTATICO.md](docs/ANALISIS_ESTATICO.md).

### Cobertura de código

Indica qué porcentaje del código ejecutaron las pruebas. Una cobertura alta no garantiza que las pruebas sean buenas, pero una cobertura baja sí señala código que nadie está probando.

PHPUnit genera el reporte, pero no tiene una opción para fallar cuando el porcentaje es bajo. Por eso el proyecto incluye `bin/verificar-cobertura.php`: lee el reporte en formato Clover y termina con error si no se alcanza el mínimo. Así el pipeline se detiene.

### CI/CD y pipeline

- **Integración continua (CI)**: cada cambio se verifica automáticamente al llegar al repositorio.
- **Despliegue continuo (CD)**: si la verificación pasa, el cambio se publica sin intervención manual. Aquí el despliegue es simulado: imprime `Desplegado` en el runner.
- **Pipeline**: la secuencia de pasos automáticos que recorre un cambio. En GitHub Actions se llama **workflow** y se define en `.github/workflows/`.

| Término | Qué es |
|---|---|
| Workflow | El archivo YAML completo |
| Evento (`on`) | Lo que dispara el workflow: un push, un pull request |
| Job | Un grupo de pasos que corre en su propia máquina virtual |
| Runner | La máquina virtual donde corre un job (`ubuntu-latest`) |
| Step | Un paso dentro de un job: una acción (`uses`) o un comando (`run`) |
| `needs` | Hace que un job espere a que otros terminen bien |

## El pipeline al iniciar

`.github/workflows/ci-cd.yml` tiene tres jobs:

1. **Pruebas unitarias**: instala PHP 8.4 y las dependencias, y corre `composer test:unit`.
2. **Pruebas BDD**: instala PHP 8.4 y las dependencias, y corre `composer test:bdd`. Ejecuta el ejemplo de promedio móvil y, cuando los agreguen, sus escenarios de alertas.
3. **Despliegue (simulado)**: corre solo si los dos jobs anteriores pasaron y solo en un push a `main`. Imprime `Desplegado`.

Los dos jobs de pruebas corren en paralelo, cada uno en su propia máquina. En la actividad agregan análisis estático y cobertura, y hacen que el despliegue dependa de los cuatro jobs de verificación.

## Clean Code

*Clean Code* (código limpio) es código que otra persona puede leer, entender y cambiar sin miedo. No es una herramienta: es un conjunto de principios que el análisis estático ayuda a vigilar y que las revisiones estáticas comprueban.

| Principio | Qué significa | Dónde se ve en este proyecto |
|---|---|---|
| Nombres que explican | El nombre dice qué es o qué hace, sin necesidad de un comentario | `$erroresAbsolutos` dice qué contiene el arreglo; `debeGenerarAlerta` se lee como una pregunta y devuelve `true` o `false` |
| Una sola responsabilidad | Cada función hace una cosa; si hace dos, se divide | `CalculadoraPronosticos` solo calcula; `public/index.php` solo traduce peticiones HTTP. Ningún método de la calculadora lee `$_SERVER` ni imprime |
| Sin duplicación | Cada regla está escrita en un solo lugar | `mad` y `mape` necesitan las mismas validaciones y las toman de `validarSeries`, en lugar de repetirlas |
| Sin números mágicos | Un número que significa algo para el negocio lleva nombre | las pruebas no repiten la serie `[120, 132, 128, 141, 138, 150]` en cada método: `PromedioMovilTest` la define una vez en la constante `SERIE`. El `100` de `mape` se deja escrito porque es la definición de porcentaje, no un parámetro del negocio |
| Comentarios que explican el porqué | El código ya dice qué hace; el comentario dice por qué se decidió así | el comentario de `debeGenerarAlerta` no repite que compara con `>=`: explica que es una regla acordada con Producción y que un error igual al umbral sí alerta |
| Manejo explícito de errores | Un dato inválido produce un error con un mensaje claro, no un resultado silencioso ni un error genérico | con series vacías, `mad` lanza `InvalidArgumentException('Se necesita al menos un periodo')` en lugar de dejar escapar un `DivisionByZeroError` (ver el ciclo 4 de `docs/EJEMPLO_TDD.md`), y `public/index.php` convierte esa excepción en una respuesta 400 con el mensaje |

Un ejemplo tomado del propio código:

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

    /**
     * Indica si un error de pronóstico debe disparar una alerta.
     *
     * Regla acordada con Producción: la alerta se activa cuando el error es mayor
     * o igual al umbral. Un error exactamente igual al umbral sí alerta.
     */
    public function debeGenerarAlerta(float $errorPorcentual, float $umbral): bool
    {
        if ($umbral < 0 || $umbral > 100) {
            throw new InvalidArgumentException('El umbral debe estar entre 0 y 100');
        }

        return $errorPorcentual >= $umbral;
    }
```

En estos dos métodos se ven cinco de los seis principios: nombres que explican (`$erroresAbsolutos`, `debeGenerarAlerta`), una sola responsabilidad (calcular, sin saber nada de HTTP), ninguna duplicación (`validarSeries`), un comentario que explica el porqué (la regla acordada) y errores explícitos con un mensaje que dice qué está mal.

## Revisiones estáticas

Una **revisión** es el examen de un producto de trabajo (código, requisitos, escenarios, un plan de pruebas) hecho **por personas**, sin ejecutarlo. Es estática, como el análisis estático, pero no la hace una herramienta:

| | Análisis estático automatizado | Revisión estática |
|---|---|---|
| Quién revisa | Una herramienta (PHPStan, en este proyecto) | Personas |
| Qué revisa | Solo código, contra reglas fijas | Cualquier producto de trabajo: código, requisitos, escenarios, documentos |
| Qué encuentra | Defectos que se pueden describir con una regla: variables sin usar, complejidad, nombres | Además, lo que una regla no ve: un requisito ambiguo, un caso que falta, un nombre que confunde, un diseño difícil de cambiar |
| Cuándo | En cada cambio, en segundos (en el pipeline) | Cuando el equipo lo planifica; cuesta tiempo de personas |

Las dos se complementan: la herramienta revisa lo mecánico en cada cambio y deja a las personas lo que requiere criterio.

**Tipos de revisión** (adaptación del temario ISTQB CTFL v4.0, sección 3.2.4). Van de la menos a la más formal:

| Tipo | Cómo es | Objetivo principal | Ejemplo en este proyecto |
|---|---|---|---|
| Revisión informal | No sigue un proceso definido ni exige dejar registro | Detectar anomalías | Pedirle a un compañero que lea la función nueva antes del commit |
| *Walkthrough* (recorrido) | La dirige el autor, que recorre el producto con los revisores; la revisión individual previa es opcional | Evaluar la calidad, generar confianza, enseñar, llegar a acuerdos, generar ideas y detectar anomalías | El autor recorre con Producción, línea por línea, el `.feature` de alertas para confirmar que dice lo que se acordó |
| Revisión técnica | La hacen revisores con conocimiento técnico y la dirige un moderador | Llegar a un acuerdo y tomar decisiones sobre un problema técnico; también detectar anomalías | El equipo decide si la complejidad máxima debe seguir en 5 |
| Inspección | La más formal: sigue el proceso completo, con roles definidos y métricas; el autor no puede ser líder ni secretario de la revisión | Encontrar el mayor número posible de anomalías | Inspeccionar el cálculo del MAPE con un checklist antes de entregarlo a Producción |

El temario describe un proceso genérico (sección 3.2.2): **planificación** (alcance, criterios de salida), **inicio** (que cada participante tenga el material y sepa su rol), **revisión individual** (cada revisor anota anomalías, por ejemplo con un checklist), **comunicación y análisis** (se discute cada anomalía y se decide qué hacer) y **corrección e informe**. Los roles principales (sección 3.2.3) son gerente, autor, moderador, secretario (quien registra), revisor y líder de la revisión. Cuanto más formal es la revisión, más de ese proceso se cumple.

En la actividad (Paso 8.5) hacen una revisión con el checklist de Clean Code.

## Testing en metodologías ágiles

En un equipo ágil las pruebas no son una fase al final: ocurren durante todo el sprint y son responsabilidad de todo el equipo (el temario ISTQB lo llama *whole team approach*, sección 1.5.2). Así encaja este proyecto en un sprint:

| Momento del sprint | Qué pasa | Qué parte del proyecto lo representa |
|---|---|---|
| Refinamiento de la historia | Producción, desarrollo y quien prueba escriben juntos la historia ("Como planeador de producción quiero una alerta…") y sus criterios de aceptación. Quien prueba pregunta por los casos límite: ¿un error igual al umbral alerta? | Los escenarios de `features/` |
| Desarrollo | Cada función se construye con TDD; los escenarios se automatizan | `docs/EJEMPLO_TDD.md`, `tests/Unit/`, los pasos BDD |
| Integración continua | Cada push ejecuta el pipeline | `.github/workflows/ci-cd.yml` |
| Revisión del sprint | Se muestra lo terminado; los escenarios en verde son la evidencia de que se cumplieron los criterios | El job *Pruebas BDD* del pipeline |
| Retrospectiva | El equipo revisa qué mejorar: por ejemplo, ajustar una regla del análisis estático | `docs/ANALISIS_ESTATICO.md` |

Tres ideas que conviene recordar:

- **Quien prueba participa desde la definición de la historia.** Encontrar la ambigüedad de "¿igual al umbral alerta o no?" cuando se escribe la historia cuesta una conversación; encontrarla en producción cuesta un defecto. Es el principio de probar temprano (*shift left*, sección 2.1.5 del temario).
- **Los escenarios BDD son criterios de aceptación ejecutables.** El temario (sección 4.5.2) describe el formato Dado/Cuando/Entonces como una de las dos formas habituales de escribir criterios de aceptación. Aquí, además, se ejecutan: si el código deja de cumplir el criterio, el pipeline lo dice.
- **El pipeline en verde es parte de la *Definition of Done*.** Una historia está terminada cuando, además de funcionar, cumple lo que el equipo acordó. Para este proyecto, una *Definition of Done* razonable sería: pruebas unitarias y escenarios BDD en verde, análisis estático sin hallazgos, cobertura de 90 % o más, código revisado por otra persona y el pipeline en verde en `main`. Si una de esas condiciones falla, la historia no está terminada, aunque "funcione en mi equipo".

Este es también el sentido de las **pruebas continuas en DevOps** (sección 2.1.4 del temario): el pipeline da retroalimentación rápida en cada cambio y reduce el riesgo de regresión, pero no elimina las pruebas manuales ni las revisiones desde el punto de vista de quien usa el sistema.

## Tipos de prueba de la Unidad 2 en este proyecto

| Tema de la unidad | Dónde está en el proyecto | Cómo se practica |
|---|---|---|
| TDD | `docs/EJEMPLO_TDD.md` (ciclo completo de `mad`) | ACTIVIDAD, Paso 2 (suavización exponencial) y Paso 8.1 (`mse`) |
| BDD | `features/promedio_movil.feature` y `features/bootstrap/PromedioMovilContext.php` | `composer test:bdd`; ACTIVIDAD, Pasos 4 y 8.3 |
| Pruebas unitarias | `tests/Unit/` | `composer test:unit`; ACTIVIDAD, Pasos 3 y 8.2 |
| Pruebas funcionales | Los escenarios BDD (sección "Pruebas funcionales" de este README) | `composer test:bdd`; ACTIVIDAD, Pasos 4 y 8.3 |
| Análisis estático | `phpstan.neon`; guía en `docs/ANALISIS_ESTATICO.md` | `composer lint`; ACTIVIDAD, Pasos 5 y 8.4 |
| Revisiones estáticas | Sección "Revisiones estáticas" de este README y checklist de la ACTIVIDAD | ACTIVIDAD, Paso 8.5 |
| Clean Code | Sección "Clean Code" de este README y el código de las reglas de negocio | ACTIVIDAD, refactor del Paso 2.3 y Paso 8.5 |
| Pruebas continuas en DevOps | `.github/workflows/ci-cd.yml` | ACTIVIDAD, Paso 7 |
| Testing en ágil | Sección "Testing en metodologías ágiles" de este README | ACTIVIDAD, Paso 7.5 y preguntas del informe (Paso 9) |

## Documentación oficial

- PHP: https://www.php.net/manual/es/
- Composer: https://getcomposer.org/doc/
- PHPUnit: https://docs.phpunit.de/
- Behat: https://docs.behat.org/
- PHPStan: https://phpstan.org/user-guide/getting-started
- PCOV: https://github.com/krakjoe/pcov
- Gherkin: https://cucumber.io/docs/gherkin/reference/
- GitHub Actions: https://docs.github.com/actions
- Acción setup-php: https://github.com/shivammathur/setup-php
