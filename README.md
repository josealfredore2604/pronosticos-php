# Pronósticos de demanda · PHP con Composer

Proyecto base del curso de Pruebas de Software. Calcula pronósticos de demanda y mide qué tan lejos quedaron de la demanda real, con las mismas reglas del proyecto nuclear "Sistema web para la predicción de la demanda en producción industrial". Incluye una API HTTP mínima que corre con el servidor incorporado de PHP.

No usa bases de datos, contenedores, frameworks ni servidores web externos: solo PHP y Composer.

La actividad que deben desarrollar está en [ACTIVIDAD.md](ACTIVIDAD.md).

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
git clone <url-de-este-repositorio>
cd pronosticos-php

composer install
composer start
```

`composer install` descarga las dependencias en `vendor/` y crea `composer.lock` con las versiones exactas que instaló. `composer start` levanta la API en http://localhost:8000 con el servidor incorporado de PHP.

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

Al iniciar el proyecto hay 9 pruebas unitarias y todas pasan.

Comandos que van a usar en la actividad (todos están en la sección `scripts` de `composer.json`):

| Comando | Qué hace |
|---|---|
| `composer test:unit` | Pruebas unitarias con PHPUnit |
| `composer test:bdd` | Escenarios BDD con Behat (los crean ustedes) |
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
├── features/                        Escenarios BDD (vacía al iniciar)
├── bin/
│   └── verificar-cobertura.php      Verifica el mínimo de cobertura
├── composer.json                    Dependencias, autoload y scripts
├── phpunit.xml                      Configuración de PHPUnit
├── phpstan.neon                     Configuración de PHPStan
├── README.md
└── ACTIVIDAD.md
```

## Herramientas

| Herramienta | Para qué | Dónde se configura |
|---|---|---|
| [Composer](https://getcomposer.org/doc/) | Dependencias, autoload de clases y scripts | `composer.json` |
| [PHPUnit](https://docs.phpunit.de/) | Ejecuta las pruebas unitarias y mide la cobertura | `phpunit.xml` |
| [Behat](https://docs.behat.org/) | Ejecuta escenarios escritos en Gherkin | `features/` |
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

### BDD (desarrollo guiado por comportamiento)

El comportamiento esperado se describe en lenguaje natural con el formato **Dado / Cuando / Entonces** (Gherkin), antes de programarlo. Una persona del negocio puede leer el escenario y confirmar si es lo que necesita. En Behat, cada línea del escenario se conecta con un método de la clase `FeatureContext`.

La primera línea del archivo `.feature` debe ser `# language: es` para usar las palabras clave en español. Si esa línea no está de primera, el archivo se lee en inglés y "Característica", "Dado" o "Cuando" no se reconocen.

### Análisis estático

Revisa el código sin ejecutarlo. PHPStan encuentra métodos que no existen, tipos incompatibles, variables sin definir y código que nunca se ejecuta. Trabaja por niveles, de 0 a 10; este proyecto usa el nivel 6, que además exige tipos en parámetros y retornos y que los arreglos documenten qué contienen (`@param list<int|float> $serie`).

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

`.github/workflows/ci-cd.yml` tiene dos jobs:

1. **Pruebas unitarias**: instala PHP 8.4 y las dependencias, y corre `composer test:unit`.
2. **Despliegue (simulado)**: corre solo si el job anterior pasó y solo en un push a `main`. Imprime `Desplegado`.

En la actividad agregan análisis estático, pruebas BDD y cobertura, y hacen que el despliegue dependa de todo.

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
