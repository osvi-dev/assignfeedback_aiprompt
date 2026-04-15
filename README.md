# assignfeedback_aiprompt

Plugin de feedback para Moodle que permite a los profesores generar retroalimentación automática sobre las entregas de sus estudiantes utilizando inteligencia artificial a través de [Ollama](https://ollama.com/).

---

## 📋 Descripción

`assignfeedback_aiprompt` es un plugin de tipo **Assignment Feedback** para Moodle. Cuando un profesor califica una tarea, puede hacer clic en un botón para que la IA analice el documento PDF entregado por el estudiante y genere un feedback personalizado basado en un prompt previamente configurado. El profesor puede revisar, editar y guardar ese feedback antes de que el estudiante lo vea.

### Características principales

- Generación automática de feedback usando modelos de lenguaje locales (Ollama).
- Extracción de texto desde archivos PDF enviados por los estudiantes.
- El profesor puede editar el feedback generado antes de guardarlo.
- El feedback se almacena en la base de datos de Moodle y se muestra al estudiante en su calificación.
- Configuración flexible: URL del servidor, modelo de IA y timeout ajustables desde el panel de administración.

---

## 🗂️ Estructura del proyecto

```
assignfeedback_aiprompt/
├── ajax_generate_feedback.php   # Endpoint AJAX para generar el feedback
├── locallib.php                 # Clase principal del plugin (formulario, guardar, mostrar)
├── settings.php                 # Configuración del plugin en el panel de administración
├── version.php                  # Metadatos del plugin (versión, dependencias)
├── classes/
│   └── ollama_client.php        # Cliente HTTP para comunicarse con la API de Ollama
├── db/
│   ├── install.xml              # Esquema de base de datos (tabla assignfeedback_aiprompt)
│   └── upgrade.php              # Script de actualización de base de datos
├── js/
│   └── feedback_generator.js    # Lógica del botón y petición AJAX en el frontend
└── lang/
    └── en/
        └── assignfeedback_aiprompt.php  # Cadenas de idioma
```

---

## ✅ Requisitos

| Requisito | Versión mínima |
|-----------|---------------|
| Moodle    | 5.0 (build 2024042200) |
| PHP       | 7.4+ |
| Ollama    | Cualquier versión con API `/api/generate` |
| pdftotext | Recomendado para extraer texto de PDFs (`poppler-utils`) |

> **Nota:** El plugin depende del módulo estándar `mod_assign` (actividades de tipo Tarea).

---

## 🚀 Instalación

### 1. Copiar el plugin

Coloca la carpeta del plugin en el directorio de feedback de tareas de Moodle:

```
{moodle_root}/mod/assign/feedback/aiprompt/
```

### 2. Instalar en Moodle

Accede a tu sitio Moodle como administrador y navega a:

```
Administración del sitio → Notificaciones
```

Moodle detectará el plugin nuevo y ejecutará el script de instalación de la base de datos automáticamente.

### 3. Instalar Ollama (si no está instalado)

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

Descarga un modelo (por ejemplo, DeepSeek R1):

```bash
ollama pull deepseek-r1:7b
```

Verifica que el servidor esté corriendo:

```bash
ollama serve
```

### 4. (Opcional) Instalar pdftotext

Para extraer el texto de los archivos PDF de los estudiantes:

```bash
# Debian / Ubuntu
sudo apt install poppler-utils

# CentOS / RHEL
sudo yum install poppler-utils
```

---

## ⚙️ Configuración

Una vez instalado, configura el plugin desde:

```
Administración del sitio → Plugins → Feedback de tarea → Feedback con IA
```

| Parámetro | Descripción | Valor por defecto |
|-----------|-------------|-------------------|
| **URL de Ollama** | Dirección del servidor Ollama | `http://localhost:11434` |
| **Modelo de Ollama** | Nombre del modelo a usar | `deepseek-r1:7b` |
| **Timeout (segundos)** | Tiempo máximo de espera para la respuesta | `120` |
| **Habilitado por defecto** | Si el plugin está activo en tareas nuevas | Sí |

---

## 📝 Dependencia: Plugin `local_prompt_tarea`

Este plugin **requiere** que exista un plugin local llamado `local_prompt_tarea` que almacene los prompts configurados para cada tarea en la tabla `local_prompt_tarea`.

Cada registro de esa tabla debe contener al menos:
- `assignid` — ID de la tarea de Moodle.
- `prompt` — Texto del prompt que se enviará a la IA.

Sin un prompt configurado para la tarea, el plugin mostrará una advertencia y no permitirá generar feedback.

---

## 🔄 Flujo de uso

```
Profesor abre la calificación de un estudiante
        │
        ▼
Se muestra el formulario con el botón "Evaluar práctica con IA"
        │
        ▼
Profesor hace clic en el botón
        │
        ▼
AJAX → ajax_generate_feedback.php
  1. Verifica permisos (mod/assign:grade)
  2. Obtiene el prompt de local_prompt_tarea
  3. Extrae texto del PDF del estudiante (pdftotext)
  4. Envía prompt + texto a Ollama
  5. Guarda el feedback en assignfeedback_aiprompt
        │
        ▼
El feedback aparece en el textarea (editable)
        │
        ▼
El profesor revisa, edita y guarda la calificación
        │
        ▼
El estudiante ve el feedback en su calificación
```

---

## 🗃️ Esquema de base de datos

### Tabla: `assignfeedback_aiprompt`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT | Clave primaria |
| `assignment` | INT | ID de la tarea (`assign.id`) |
| `userid` | INT | ID del estudiante |
| `aifeedback` | TEXT | Texto del feedback generado por IA |
| `isedited` | TINYINT | `1` si el profesor editó el feedback, `0` si no |
| `timecreated` | INT | Timestamp de creación |
| `timemodified` | INT | Timestamp de última modificación |

---

## 🧩 Arquitectura del plugin

### `locallib.php` — Clase `assign_feedback_aiprompt`

Hereda de `assign_feedback_plugin` y implementa los métodos que Moodle invoca en el ciclo de vida del feedback:

| Método | Descripción |
|--------|-------------|
| `get_name()` | Devuelve el nombre del plugin |
| `get_form_elements()` | Renderiza el botón y el textarea en el formulario de calificación |
| `save()` | Guarda el feedback en la base de datos |
| `view()` | Muestra el feedback al estudiante |
| `is_empty()` | Indica si hay feedback que mostrar |
| `view_summary()` | Muestra un resumen del feedback |

### `classes/ollama_client.php` — Clase `ollama_client`

Cliente HTTP que se comunica con la API REST de Ollama:

| Método | Descripción |
|--------|-------------|
| `generate_feedback($prompt, $context)` | Envía el prompt y el contexto a Ollama y retorna la respuesta |
| `test_connection()` | Verifica si el servidor Ollama está disponible |

### `js/feedback_generator.js`

Script de frontend que:
1. Escucha el clic en el botón `#id_generate_ai_feedback`.
2. Deshabilita el botón y muestra un indicador de carga.
3. Realiza una petición `POST` vía `XMLHttpRequest` al endpoint AJAX.
4. Inserta el feedback generado en el textarea o muestra el error correspondiente.

---

## 🔐 Seguridad y permisos

- El endpoint AJAX verifica que el usuario tenga la capacidad **`mod/assign:grade`** sobre el contexto del módulo antes de generar feedback.
- Se valida la sesión de Moodle (`sesskey`) en cada petición AJAX.
- Solo los administradores pueden cambiar la configuración del plugin.

---

## 🛠️ Desarrollo y contribución

### Añadir soporte para otro tipo de archivos

Actualmente el plugin solo procesa el primer archivo PDF de la entrega. Para añadir soporte a otros formatos (`.docx`, `.txt`, etc.), modifica el bucle de archivos en `ajax_generate_feedback.php`.

### Cambiar el modelo de IA

Desde el panel de administración puedes cambiar el modelo en cualquier momento. Asegúrate de haber descargado el modelo con `ollama pull <nombre-del-modelo>` antes de configurarlo.

### Añadir idiomas

Crea el archivo de idioma correspondiente en:

```
lang/<codigo_idioma>/assignfeedback_aiprompt.php
```

---

## 📄 Licencia

Este plugin está basado en la arquitectura estándar de plugins de Moodle. Consulta [https://moodle.org/plugins](https://moodle.org/plugins) para más información sobre el desarrollo de plugins.

---

## 👤 Autor

Desarrollado como parte de un proyecto académico para la automatización del feedback en tareas de Moodle mediante inteligencia artificial generativa local.
