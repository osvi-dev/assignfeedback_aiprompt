# assignfeedback_aiprompt

Plugin de **feedback para tareas de Moodle** que genera retroalimentación automática sobre las entregas de los estudiantes utilizando inteligencia artificial local a través de [Ollama](https://ollama.com/).

---

## 📋 Descripción

`assignfeedback_aiprompt` es un plugin de tipo **Assignment Feedback** para Moodle. Cuando un profesor abre la pantalla de calificación de un estudiante, puede hacer clic en un botón para que la IA analice el PDF entregado y genere un feedback personalizado, basado en un prompt que el profesor configuró previamente en la tarea.

El profesor puede **revisar, editar y guardar** el feedback antes de que el estudiante lo vea.

> ⚠️ **Dependencia obligatoria:** Este plugin requiere que el plugin [`local_prompt_tarea`](../prompt_tarea/) esté instalado y que el profesor haya configurado un prompt en la tarea.

---

## ✨ Características principales

- 🤖 Generación automática de feedback con modelos de lenguaje local (Ollama / DeepSeek, Llama, etc.).
- 📄 Extracción de texto desde archivos PDF de los estudiantes (`pdftotext`).
- ✏️ El profesor puede editar el feedback antes de guardarlo.
- 💾 El feedback se persiste en la base de datos y se muestra al estudiante en su calificación.
- ⚙️ Configuración flexible: URL del servidor Ollama, modelo de IA y timeout ajustables desde el panel de administración.

---

## 🗂️ Estructura del proyecto

```
mod/assign/feedback/aiprompt/
├── ajax_generate_feedback.php   # Endpoint AJAX para generar el feedback con IA
├── locallib.php                 # Clase principal del plugin (formulario, guardar, mostrar)
├── settings.php                 # Configuración del plugin en el panel de administración
├── version.php                  # Metadatos del plugin (versión, dependencias)
├── classes/
│   └── ollama_client.php        # Cliente HTTP para comunicarse con la API de Ollama
├── db/
│   ├── install.xml              # Esquema de base de datos
│   └── upgrade.php              # Script de actualización de BD
├── js/
│   └── feedback_generator.js    # Lógica del botón y petición AJAX en el frontend
└── lang/
    └── en/
        └── assignfeedback_aiprompt.php  # Cadenas de idioma
```

---

## ✅ Requisitos

| Requisito | Versión / Detalle |
|-----------|-------------------|
| Moodle | 5.0 (build `2024042200`) |
| PHP | 7.4+ |
| `mod_assign` | 2024042200 (incluido en Moodle 5.0) |
| Ollama | Cualquier versión con API `/api/generate` |
| `pdftotext` | Recomendado — paquete `poppler-utils` |
| `local_prompt_tarea` | Debe estar instalado y configurado |

---

## 🚀 Instalación

### 1. Copiar el plugin

```bash
# Ruta de instalación dentro de Moodle
{moodle_root}/mod/assign/feedback/aiprompt/
```

### 2. Instalar `local_prompt_tarea` (dependencia)

```bash
{moodle_root}/local/prompt_tarea/
```

### 3. Activar en Moodle

Como administrador, ve a:

```
Administración del sitio → Notificaciones
```

Moodle detectará ambos plugins y ejecutará los scripts de base de datos.

### 4. Instalar Ollama

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

Descarga un modelo de lenguaje:

```bash
ollama pull deepseek-r1:7b
# Otras opciones: llama3, mistral, phi3, gemma2
```

Verifica que el servidor esté activo:

```bash
ollama serve
# El servidor escucha en http://localhost:11434 por defecto
```

### 5. (Recomendado) Instalar `pdftotext`

Necesario para extraer el texto de los PDFs entregados por los estudiantes:

```bash
# Debian / Ubuntu
sudo apt install poppler-utils

# CentOS / RHEL / Fedora
sudo yum install poppler-utils
```

---

## ⚙️ Configuración

Una vez instalado, configura el plugin desde:

```
Administración del sitio → Plugins → Feedback de tarea → Feedback con IA (AI Prompt)
```

| Parámetro | Descripción | Valor por defecto |
|-----------|-------------|-------------------|
| **URL de Ollama** | Dirección del servidor Ollama | `http://localhost:11434` |
| **Modelo de Ollama** | Nombre del modelo a usar | `deepseek-r1:7b` |
| **Timeout (segundos)** | Tiempo máximo de espera para la respuesta de la IA | `120` |
| **Habilitado por defecto** | Si el plugin estará activo en nuevas tareas | `Sí` |

---

## 🔄 Flujo de uso

```
1. El profesor configura un prompt en la tarea (via local_prompt_tarea)
         │
         ▼
2. El estudiante entrega un archivo PDF en la tarea
         │
         ▼
3. El profesor abre la pantalla de calificación del estudiante
         │
         ▼
4. El formulario muestra el botón "Evaluar práctica con IA"
         │
         ▼
5. El profesor hace clic → Petición AJAX a ajax_generate_feedback.php
   ├─ Verifica permisos (mod/assign:grade)
   ├─ Lee el prompt desde local_prompt_tarea
   ├─ Extrae el texto del PDF con pdftotext
   ├─ Envía prompt + texto a Ollama → recibe feedback
   └─ Guarda el feedback en assignfeedback_aiprompt (BD)
         │
         ▼
6. El feedback aparece en el textarea (editable por el profesor)
         │
         ▼
7. El profesor revisa, edita si es necesario y guarda la calificación
         │
         ▼
8. El estudiante ve el feedback en su calificación
```

---

## 🗃️ Esquema de base de datos

### Tabla: `assignfeedback_aiprompt`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT (PK) | Clave primaria autoincremental |
| `assignment` | INT (FK) | ID de la tarea (`assign.id`) |
| `userid` | INT | ID del estudiante evaluado |
| `aifeedback` | TEXT | Texto del feedback generado por la IA |
| `isedited` | TINYINT | `1` si el profesor editó el feedback, `0` si no |
| `timecreated` | INT | Timestamp Unix de creación |
| `timemodified` | INT | Timestamp Unix de última modificación |

> Un par `(assignment, userid)` identifica un feedback único por alumno por tarea.

---

## 🧩 Arquitectura del plugin

### `locallib.php` — Clase `assign_feedback_aiprompt`

Extiende `assign_feedback_plugin` e implementa el ciclo de vida del feedback en Moodle:

| Método | Descripción |
|--------|-------------|
| `get_name()` | Devuelve el nombre localizado del plugin |
| `get_form_elements()` | Renderiza el botón y el textarea en el formulario de calificación |
| `save()` | Persiste el feedback (generado o editado) en la base de datos |
| `view()` | Renderiza el feedback formateado para el estudiante |
| `is_empty()` | Indica si hay feedback guardado para mostrar al estudiante |
| `view_summary()` | Muestra un resumen del feedback en la lista de calificaciones |

### `classes/ollama_client.php` — Clase `ollama_client`

Cliente HTTP que se comunica con la API REST de Ollama vía cURL:

| Método | Parámetros | Descripción |
|--------|------------|-------------|
| `generate_feedback($prompt, $context)` | `string`, `string` | Envía el prompt más el texto del PDF a Ollama y retorna la respuesta generada |
| `test_connection()` | — | Verifica si el servidor Ollama está disponible (GET `/api/tags`) |

La configuración (URL, modelo, timeout) se lee automáticamente desde los ajustes del plugin con `get_config()`.

### `ajax_generate_feedback.php`

Endpoint AJAX que orquesta todo el proceso de generación:

1. Autentica la sesión Moodle y verifica el permiso `mod/assign:grade`.
2. Lee el prompt desde `local_prompt_tarea`.
3. Obtiene la entrega del estudiante y extrae el texto del PDF.
4. Llama a `ollama_client::generate_feedback()`.
5. Guarda el resultado en `assignfeedback_aiprompt` (crea o actualiza el registro).
6. Retorna JSON: `{ success: true, feedback: "..." }` o `{ success: false, error: "..." }`.

### `js/feedback_generator.js`

Script de frontend que:

1. Escucha el clic en el botón `#id_generate_ai_feedback`.
2. Deshabilita el botón y muestra un indicador de carga en `#ai_feedback_status`.
3. Realiza una petición `POST` mediante `XMLHttpRequest` al endpoint AJAX.
4. Inserta el feedback en el textarea `#id_assignfeedbackaiprompt`, o muestra el error.

---

## 🔐 Seguridad y permisos

| Control | Detalle |
|---------|---------|
| Autenticación | Se requiere sesión Moodle activa (`require_login()`) |
| Autorización | El usuario debe tener la capacidad `mod/assign:grade` en el contexto del módulo |
| Administración | Solo los administradores pueden modificar los ajustes del plugin |

---

## 🛠️ Desarrollo y extensión

### Añadir soporte para otros formatos de archivo

Actualmente el plugin procesa el primer archivo PDF de la entrega. Para añadir soporte a `.docx`, `.txt`, etc., modifica el bucle de procesamiento de archivos en `ajax_generate_feedback.php` (líneas ~54–75).

### Cambiar el modelo de IA

Desde el panel de administración puedes cambiar el modelo en cualquier momento. Antes de usarlo, descárgalo:

```bash
ollama pull llama3:8b
ollama pull mistral:7b
ollama pull phi3:mini
```

### Añadir idiomas

Crea el archivo de traducciones en:

```
lang/<codigo_idioma>/assignfeedback_aiprompt.php
```

Ejemplo para español (`es`):

```
lang/es/assignfeedback_aiprompt.php
```

---

## 📄 Versión

| Atributo | Valor |
|----------|-------|
| Componente | `assignfeedback_aiprompt` |
| Versión | `2025011107` |
| Release | `1.0` |
| Madurez | Estable (`MATURITY_STABLE`) |
| Requiere | Moodle 5.0+ / `mod_assign` |

---

## 👤 Autor

Desarrollado como parte de un proyecto académico para la **automatización del feedback en tareas de Moodle** mediante inteligencia artificial generativa local, eliminando la dependencia de servicios externos en la nube.
