# PRD — RAG Compras Públicas

| Campo | Valor |
|---|---|
| Producto | Asistente de consulta documental sobre compras públicas (RAG) |
| Código | `rag-compras-publicas` |
| Versión del documento | 1.0 (borrador para validación) |
| Fecha | 2026-08-31 |
| Estado | Propuesta — pendiente de validación con el repositorio original |

> **Nota de procedencia.** El repositorio `marcoorellanacolque12-alt/rag-compras-publicas` no fue accesible al redactar este documento (HTTP 404: privado o inexistente en esa ruta). Por lo tanto este PRD **no** describe código existente: es una especificación de referencia construida a partir del dominio y del nombre del proyecto. Cada supuesto está marcado en §11. Al obtener acceso al repositorio, este documento debe reconciliarse contra la implementación real.

---

## 1. Resumen ejecutivo

La información de compras públicas es abundante y formalmente abierta, pero **operativamente inaccesible**: vive en portales con buscadores rígidos, en PDFs escaneados de cientos de páginas (bases, términos de referencia, pliegos absolutorios, contratos, adendas) y en normativa que cambia. Responder una pregunta simple —"¿qué exige esta entidad para acreditar experiencia del postor?", "¿cuántos procesos declaró desiertos esta municipalidad este año?"— cuesta horas de lectura manual.

`rag-compras-publicas` es un sistema de **Retrieval-Augmented Generation** que ingiere convocatorias, documentos y datos estructurados de compras públicas, los indexa con recuperación híbrida (léxica + semántica) y responde preguntas en lenguaje natural **con citas verificables al documento y página de origen**.

La tesis central del producto: en este dominio, **una respuesta sin cita no vale nada**. El valor no está en la fluidez de la respuesta sino en reducir el tiempo hasta la evidencia.

## 2. Problema

| # | Problema | Impacto |
|---|---|---|
| P1 | Los buscadores de los portales operan por metadatos (número de proceso, entidad, fecha), no por contenido de los documentos | Es imposible preguntar "qué procesos exigen certificación ISO 9001" |
| P2 | Los documentos clave son PDFs, muchos escaneados o exportados sin capa de texto | El contenido es invisible para cualquier búsqueda |
| P3 | Un proceso genera decenas de documentos versionados (bases, integradas, absolución de consultas, adendas) | Se responde con la versión equivocada del documento |
| P4 | La normativa aplicable cambia y depende de la fecha y el tipo de procedimiento | Respuestas correctas ayer son incorrectas hoy |
| P5 | Los LLM generalistas alucinan montos, plazos, números de proceso y artículos de ley | Riesgo alto: una cifra inventada puede costar una postulación o una denuncia mal fundada |

## 3. Objetivos y métricas de éxito

### 3.1 Objetivos de producto

| # | Objetivo |
|---|---|
| O1 | Responder preguntas en lenguaje natural sobre convocatorias, documentos y adjudicaciones, con cita obligatoria |
| O2 | Reducir el tiempo de localización de evidencia de horas a menos de un minuto |
| O3 | Mantener el corpus actualizado con latencia máxima de 24 h respecto de la fuente oficial |
| O4 | Hacer auditable cada respuesta: qué fragmentos se recuperaron, de qué documento, en qué versión |

### 3.2 KPIs

| KPI | Métrica | Meta v1 | Meta v2 |
|---|---|---|---|
| K1 — Groundedness | % de afirmaciones de la respuesta soportadas por los fragmentos citados (juez LLM + muestra humana) | ≥ 92 % | ≥ 96 % |
| K2 — Recall de recuperación | `recall@10` sobre el golden set | ≥ 85 % | ≥ 92 % |
| K3 — Precisión de citas | % de citas que apuntan al documento y página correctos | ≥ 95 % | ≥ 98 % |
| K4 — Abstención correcta | % de preguntas sin evidencia en el corpus en las que el sistema dice "no encontré" en vez de inventar | ≥ 95 % | ≥ 98 % |
| K5 — Latencia | p95 de respuesta completa (streaming: primer token) | ≤ 8 s (≤ 2 s) | ≤ 5 s (≤ 1,5 s) |
| K6 — Frescura | Retraso p95 entre publicación en la fuente y disponibilidad para consulta | ≤ 24 h | ≤ 4 h |
| K7 — Costo | Costo de inferencia por consulta respondida | ≤ USD 0,05 | ≤ USD 0,02 |
| K8 — Adopción | Consultas/semana por usuario activo | ≥ 10 | ≥ 25 |

### 3.3 Anti-objetivos (qué NO es este producto)

- **No es un asesor legal.** No emite opiniones jurídicas ni recomienda impugnar. Muestra qué dicen los documentos.
- **No es un detector de corrupción.** Puede exponer señales (concentración de adjudicaciones, postor único), pero no imputa.
- **No predice** quién ganará una licitación.
- **No presenta ofertas ni interactúa con los portales oficiales** en modo escritura.

## 4. Usuarios

| Persona | Descripción | Necesidad principal | Pregunta típica |
|---|---|---|---|
| **Ana — analista de licitaciones** (PYME proveedora) | Revisa 20–40 convocatorias/semana buscando cuáles puede atender | Filtrar rápido y entender requisitos de admisibilidad | "¿Qué experiencia mínima piden en esta convocatoria y cómo se acredita?" |
| **Luis — funcionario de una unidad de abastecimiento** | Prepara bases y responde consultas de postores | Reutilizar precedentes y verificar consistencia normativa | "¿Cómo redactaron otras entidades el requisito de garantía de fiel cumplimiento para servicios?" |
| **Rita — periodista / OSC de transparencia** | Investiga patrones de contratación | Explorar y comparar con evidencia citable | "¿Qué proveedores concentraron adjudicaciones directas en esta entidad en 2025?" |
| **Marco — auditor / control interno** | Revisa expedientes | Trazabilidad total, cero alucinación | "Muéstrame todas las adendas de este contrato y qué cambió el plazo" |

Usuario primario para v1: **Ana**. El producto se optimiza para su recorrido.

## 5. Alcance

### 5.1 Dentro de alcance (v1)

- Ingesta desde **al menos una fuente oficial** vía API/datos abiertos + descarga de documentos adjuntos.
- Extracción de texto de PDF nativo y **OCR** para escaneados.
- Indexación híbrida con filtros por metadatos (entidad, fecha, monto, estado, tipo de procedimiento, rubro).
- API de consulta (`/query`) con respuesta citada y streaming.
- Interfaz web mínima: búsqueda, respuesta con citas, visor del documento en la página citada.
- Panel de ingesta: qué se descargó, qué falló, cobertura por fuente.
- Suite de evaluación automatizada sobre golden set.

### 5.2 Fuera de alcance (v1)

- Multi-tenant con aislamiento fuerte por organización (v2).
- Alertas y suscripciones ("avísame cuando salga una convocatoria de X") (v2).
- Comparación automática de versiones de bases (v2).
- Análisis de redes de proveedores / grafos societarios (v3).
- Ingesta de más de dos jurisdicciones simultáneas (v2+).
- Aplicación móvil.

## 6. Casos de uso e historias de usuario

| ID | Historia | Criterios de aceptación |
|---|---|---|
| HU-01 | Como Ana, quiero preguntar en lenguaje natural sobre una convocatoria específica para entender sus requisitos sin leer 200 páginas | La respuesta cita ≥1 fragmento con documento + página; el visor abre en esa página; si no hay evidencia, el sistema lo declara |
| HU-02 | Como Ana, quiero filtrar por entidad, rango de fechas, monto y rubro antes de preguntar | Los filtros se aplican **antes** de la recuperación, no como post-filtro sobre el top-k |
| HU-03 | Como Luis, quiero buscar cómo otras entidades redactaron una cláusula | La búsqueda devuelve fragmentos de múltiples documentos con su contexto y entidad de origen |
| HU-04 | Como Rita, quiero agregar datos (conteos, montos) sobre procesos | Las preguntas cuantitativas se responden desde el **almacén estructurado**, no desde el texto recuperado (§ RF-09) |
| HU-05 | Como Marco, quiero ver exactamente qué se le pasó al modelo | Cada respuesta expone su traza: consulta reescrita, filtros, fragmentos, puntajes, versión del prompt y del índice |
| HU-06 | Como cualquier usuario, quiero saber cuándo la información es vieja | Cada documento muestra fecha de publicación y fecha de última sincronización |
| HU-07 | Como Ana, quiero continuar la conversación ("¿y el plazo de entrega?") | El sistema resuelve referencias anafóricas contra el turno anterior antes de recuperar |

## 7. Requisitos funcionales

### Ingesta

| ID | Requisito | Prioridad |
|---|---|---|
| RF-01 | Conectores por fuente, con interfaz común (`fetch_processes`, `fetch_documents`), de modo que agregar una jurisdicción no toque el núcleo | Debe |
| RF-02 | Ingesta incremental idempotente: reejecutar no duplica ni reprocesa lo ya vigente (hash de contenido) | Debe |
| RF-03 | Preservación del documento original en almacenamiento de objetos, inmutable, con su hash | Debe |
| RF-04 | Extracción de texto con detección de PDF sin capa de texto y fallback a OCR en español | Debe |
| RF-05 | Extracción de tablas (cronogramas, cuadros de requisitos, presupuestos) preservando estructura | Debería |
| RF-06 | Versionado documental: bases originales, integradas y adendas se enlazan al mismo proceso con orden temporal y una marca de "vigente" | Debe |
| RF-07 | Reintentos con backoff y respeto de límites de la fuente; una fuente caída no bloquea las demás | Debe |
| RF-08 | Cuarentena y reporte de documentos no parseables, sin pérdida silenciosa | Debe |

### Consulta

| ID | Requisito | Prioridad |
|---|---|---|
| RF-09 | Enrutamiento de consultas: preguntas agregadas/cuantitativas se resuelven contra el almacén estructurado (SQL) y no por recuperación de texto | Debe |
| RF-10 | Recuperación híbrida: léxica (BM25/full-text en español) + densa (embeddings), fusionadas con Reciprocal Rank Fusion | Debe |
| RF-11 | Filtrado por metadatos aplicado en la consulta al índice (pre-filtro) | Debe |
| RF-12 | Reranking de los candidatos fusionados antes de construir el contexto | Debería |
| RF-13 | Reescritura de consulta: expansión de siglas del dominio, resolución de anáforas, generación de subconsultas | Debería |
| RF-14 | Generación con **cita obligatoria**: toda afirmación factual referencia `documento + página`; sin evidencia suficiente, el sistema se abstiene explícitamente | Debe |
| RF-15 | Respuesta en streaming | Debe |
| RF-16 | Traza completa por consulta, persistida y consultable (RF de auditoría) | Debe |
| RF-17 | Retroalimentación del usuario por respuesta (útil / incorrecta / cita mala) alimentando el golden set | Debería |

### Plataforma

| ID | Requisito | Prioridad |
|---|---|---|
| RF-18 | API REST documentada (OpenAPI) con autenticación por clave y límites de tasa | Debe |
| RF-19 | Panel operativo: cobertura de ingesta, tasa de OCR, fallos, costo por día | Debe |
| RF-20 | Suite de evaluación ejecutable en CI contra un golden set versionado | Debe |
| RF-21 | Reindexación completa sin caída del servicio (índice sombra + cambio atómico de alias) | Debería |

## 8. Requisitos no funcionales

| ID | Categoría | Requisito |
|---|---|---|
| RNF-01 | Rendimiento | p95 ≤ 8 s extremo a extremo; primer token ≤ 2 s; recuperación ≤ 800 ms |
| RNF-02 | Escala v1 | 500 k documentos, 20 M fragmentos, 50 consultas concurrentes |
| RNF-03 | Disponibilidad | 99,5 % mensual para la ruta de consulta. La ingesta puede degradarse sin afectar consultas |
| RNF-04 | Costo | ≤ USD 0,05 por consulta; presupuesto mensual de inferencia con alerta al 80 % |
| RNF-05 | Seguridad | Secretos fuera del repositorio; TLS obligatorio; sin PII en logs de aplicación |
| RNF-06 | Privacidad | Los documentos son públicos, pero contienen datos personales (DNI/RUT/CI, domicilios, firmas). Se aplica minimización y redacción en las salidas |
| RNF-07 | Trazabilidad | Toda respuesta reproducible: se persisten IDs de fragmentos, versión de prompt, versión de modelo, versión de índice |
| RNF-08 | Portabilidad | Todo el sistema debe levantarse localmente con `docker compose up` usando datos de muestra |
| RNF-09 | Idioma | Español (variantes regionales); el pipeline no debe degradarse con tildes, ñ ni con documentos en mayúsculas |
| RNF-10 | Accesibilidad | Interfaz WCAG 2.1 AA en los recorridos principales |
| RNF-11 | Mantenibilidad | Cobertura de pruebas ≥ 70 % en núcleo de ingesta y recuperación; tipado estático obligatorio |
| RNF-12 | Cumplimiento | Respetar términos de uso y `robots.txt` de las fuentes; identificarse con User-Agent propio y datos de contacto |

## 9. Datos

### 9.1 Fuentes candidatas

| Jurisdicción | Fuente | Tipo | Nota |
|---|---|---|---|
| Perú | SEACE / OSCE, PERÚ COMPRAS, Plataforma Nacional de Datos Abiertos | API + datos abiertos + documentos | Candidata principal si el proyecto es peruano |
| Chile | Mercado Público (ChileCompra) | API pública documentada | La más sencilla de integrar |
| Bolivia | SICOES | Portal + descargas | Requiere más scraping |
| Colombia | SECOP I/II (datos.gov.co) | API Socrata | Muy buena cobertura estructurada |
| Transversal | Normativa aplicable (ley de contrataciones y su reglamento) | Corpus documental estático versionado por fecha de vigencia | Necesario para preguntas normativas |

> **Decisión pendiente (§11-S1).** La jurisdicción objetivo determina el conector inicial. La arquitectura es agnóstica; la implementación del conector no.

### 9.2 Entidades del dominio

`Entidad contratante` · `Proceso` (convocatoria) · `Ítem` · `Documento` (con versión) · `Fragmento` · `Proveedor` · `Oferta` · `Adjudicación` · `Contrato` · `Adenda`.

### 9.3 Calidad de datos

- Los montos vienen en monedas y formatos distintos → normalización a moneda canónica + moneda original preservada.
- Nombres de entidades y proveedores llegan sin normalizar → normalización por identificador fiscal, nunca por nombre.
- Fechas con zonas horarias inconsistentes → todo se almacena en UTC con la fecha local original preservada.

## 10. Riesgos

| # | Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|---|
| R1 | Alucinación de cifras o de artículos normativos | Media | **Crítico** | Cita obligatoria; abstención por defecto; verificación de que cada número de la respuesta aparezca en algún fragmento citado |
| R2 | La fuente cambia su API o su HTML sin aviso | Alta | Alto | Conectores con pruebas de contrato ejecutadas a diario; alerta ante caída de volumen ingerido |
| R3 | Bloqueo o límite de tasa por parte del portal | Media | Alto | Respeto de límites, backoff, caché local, contacto formal con la entidad |
| R4 | OCR de mala calidad en documentos escaneados | Alta | Medio | Puntaje de confianza por documento; marcar respuestas basadas en OCR de baja confianza |
| R5 | Costo de inferencia por encima de lo previsto | Media | Medio | Caché de prompt, caché semántica de respuestas, límite por usuario, alerta presupuestaria |
| R6 | Uso del sistema como si fuera asesoría legal | Media | Alto | Advertencia visible; el producto muestra evidencia, no dictamina |
| R7 | Exposición de datos personales presentes en documentos públicos | Media | Alto | Redacción de PII en las salidas; no indexar anexos de hojas de vida cuando sean identificables |
| R8 | El golden set se vuelve obsoleto y la evaluación deja de detectar regresiones | Media | Medio | Golden set versionado, revisión trimestral, incorporación continua desde la retroalimentación |

## 11. Supuestos abiertos (a validar)

| # | Supuesto | Cómo se resuelve |
|---|---|---|
| S1 | La jurisdicción objetivo no está confirmada; el documento asume un diseño multi-jurisdicción con un conector inicial | Confirmar con el autor del repositorio |
| S2 | Se asume proyecto Python (stack dominante para RAG) | Verificar contra el repositorio real |
| S3 | Se asume uso de la API de Claude para generación | Verificar; si el repositorio ya usa otro proveedor, la arquitectura no cambia, solo el adaptador de generación |
| S4 | Se asume que no hay requisito de despliegue on-premise ni de datos que no puedan salir del país | Confirmar; afectaría la elección de modelos y de embeddings |
| S5 | Se asume acceso público a las fuentes sin convenio institucional | Confirmar |

## 12. Roadmap

### Fase 0 — Cimientos (semanas 1–2)
Esqueleto del repositorio, `docker compose` local, esquema de base de datos y migraciones, un conector de una sola fuente en modo lectura, 100 documentos de muestra ingeridos extremo a extremo. **Salida:** un `curl` que devuelve una respuesta citada.

### Fase 1 — MVP consultable (semanas 3–6)
OCR, chunking definitivo, recuperación híbrida + RRF, generación citada con abstención, API + interfaz mínima con visor de PDF, golden set inicial (100 preguntas) y evaluación en CI. **Salida:** Ana responde sus preguntas reales sin abrir el portal.

### Fase 2 — Confiable (semanas 7–10)
Reranking, reescritura de consultas, enrutamiento a SQL para preguntas agregadas, versionado de documentos y noción de "vigente", panel operativo, caché y control de costos. **Salida:** K1 ≥ 92 %, K2 ≥ 85 %.

### Fase 3 — Producto (semanas 11–16)
Segunda fuente/jurisdicción, alertas y suscripciones, comparación de versiones de bases, multi-tenant, exportación de informes citados. **Salida:** producto vendible o desplegable institucionalmente.

## 13. Criterios de aceptación de la v1

1. Ingesta continua y automática de la fuente inicial, con panel que muestre cobertura y fallos.
2. 100 % de las respuestas contienen citas o una declaración explícita de ausencia de evidencia.
3. Golden set de ≥ 100 preguntas ejecutándose en CI con umbrales que bloquean el merge ante regresión.
4. K1 ≥ 92 %, K2 ≥ 85 %, K3 ≥ 95 %, K4 ≥ 95 %, K5 p95 ≤ 8 s.
5. `docker compose up` levanta el sistema completo con datos de muestra en una máquina limpia.
6. Ninguna respuesta expone datos personales redactables ni claves.
