# language: es
Característica: Pronóstico de demanda con promedio móvil
  Como planeador de producción
  Quiero pronosticar la demanda del siguiente periodo con el promedio de los últimos periodos
  Para decidir cuánto producir sin depender de un cálculo manual

  Escenario: Pronóstico del periodo 7 con una ventana de 3 periodos
    Dada la serie de demanda 120, 132, 128, 141, 138, 150
    Cuando calculo el promedio móvil con una ventana de 3 periodos
    Entonces el pronóstico del siguiente periodo es 143 unidades

  # Un esquema se ejecuta una vez por cada fila de Ejemplos: <ventana> y <mensaje>
  # se reemplazan por los valores de la fila antes de buscar el paso que corresponde.
  Esquema del escenario: No se pronostica con una ventana que la serie no admite
    Dada la serie de demanda 120, 132, 128, 141, 138, 150
    Cuando calculo el promedio móvil con una ventana de <ventana> periodos
    Entonces se informa el error "<mensaje>"

    Ejemplos:
      | ventana | mensaje                                                  |
      | 7       | La serie tiene menos periodos que la ventana configurada |
      | 0       | La ventana debe ser un entero positivo                   |
