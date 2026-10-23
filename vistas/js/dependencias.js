/**
 * Script de Módulo: Gestión de Dependencias
 * Consumo 100% asíncrono de la API RESTful de ODIN (/api/dependencias).
 */

const API_DEPENDENCIAS_URL = "api/dependencias";
const API_USUARIOS_URL     = "api/usuarios";

let tablaDependencias = null;

$(document).ready(function() {
    if ($("#tblDependencias").length > 0) {
        inicializarTablaDependencias();
        cargarEstadisticasDependencias();
        configurarEventosTabla();
    }

    if ($("#frmGuardarDependencia").length > 0) {
        inicializarFormularioDependencia();
    }
});

/*=============================================
1. INICIALIZAR DATATABLE DE DEPENDENCIAS
=============================================*/
function inicializarTablaDependencias() {
    if ($.fn.DataTable.isDataTable("#tblDependencias")) {
        $("#tblDependencias").DataTable().destroy();
    }

    tablaDependencias = $("#tblDependencias").DataTable({
        ajax: {
            url: API_DEPENDENCIAS_URL,
            type: "GET",
            dataSrc: "data",
            error: function(xhr) {
                const mensaje = xhr.responseJSON?.message || "No fue posible cargar el listado de dependencias.";
                Swal.fire({
                    icon: "error",
                    title: "Error al cargar dependencias",
                    text: mensaje
                });
            }
        },
        columns: [
            // 1. ID
            {
                data: "id_dependencia",
                className: "text-center align-middle",
                render: function(data) {
                    return `<span class="badge badge-secondary font-weight-bold">${data}</span>`;
                }
            },
            // 2. Código
            {
                data: "codigo",
                className: "text-center align-middle",
                render: function(data, type, row) {
                    const codigo = data || row.dependencia || `DEP-0${row.id_dependencia}`;
                    return `<span class="badge badge-info px-2 py-1 font-weight-bold">${$("<div>").text(codigo).html()}</span>`;
                }
            },
            // 3. Nombre
            {
                data: "nombre",
                className: "align-middle",
                render: function(data) {
                    return `<strong>${$("<div>").text(data || "").html()}</strong>`;
                }
            },
            // 4. Encargado / Responsable
            {
                data: null,
                className: "align-middle",
                render: function(data, type, row) {
                    if (row.id_usuario_responsable && row.encargado_nombre) {
                        const rol = row.encargado_rol ? `<span class="badge badge-light border mr-1">${row.encargado_rol}</span>` : "";
                        const correo = row.encargado_correo ? `<small class="text-muted">&bull; ${row.encargado_correo}</small>` : "";
                        return `
                            <div class="d-flex align-items-center">
                                <div class="mr-2 text-primary">
                                    <i class="fas fa-user-shield" style="font-size: 1.25rem;"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark">${$("<div>").text(row.encargado_nombre).html()}</div>
                                    <div class="small">${rol}${correo}</div>
                                </div>
                            </div>`;
                    }
                    return `<span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-user-slash mr-1"></i>Sin asignar</span>`;
                }
            },
            // 5. Descripción
            {
                data: "descripcion",
                className: "align-middle",
                render: function(data) {
                    const texto = data || "Sin descripción";
                    return `<small class="text-muted d-inline-block text-truncate" style="max-width: 250px;" title="${$("<div>").text(texto).html()}">${$("<div>").text(texto).html()}</small>`;
                }
            },
            // 6. Total Usuarios
            {
                data: "total_usuarios",
                className: "text-center align-middle",
                render: function(data) {
                    return `<span class="badge badge-dark px-2 py-1">${parseInt(data, 10) || 0}</span>`;
                }
            },
            // 7. Estado
            {
                data: "estado",
                className: "text-center align-middle",
                render: function(data, type, row) {
                    const esActiva = (data === "Activo" || data === "Activa");
                    const badgeClass = esActiva ? "badge-success" : "badge-danger";
                    return `<span class="badge ${badgeClass} px-2 py-1">${esActiva ? "Activa" : "Inactiva"}</span>`;
                }
            },
            // 8. Acciones
            {
                data: null,
                className: "text-center align-middle",
                orderable: false,
                render: function(data, type, row) {
                    const idDep = parseInt(row.id_dependencia, 10);
                    const esActiva = (row.estado === "Activo" || row.estado === "Activa");
                    const nombreSeguro = $("<div>").text(row.nombre || "").html();

                    const btnEstado = esActiva
                        ? `<button type="button"
                                   class="btn btn-warning btn-sm btnEstadoDependencia mr-1"
                                   data-id-dependencia="${idDep}"
                                   data-estado-dependencia="Inactivo"
                                   title="Desactivar dependencia">
                               <i class="fas fa-power-off"></i>
                           </button>`
                        : `<button type="button"
                                   class="btn btn-success btn-sm btnEstadoDependencia mr-1"
                                   data-id-dependencia="${idDep}"
                                   data-estado-dependencia="Activo"
                                   title="Activar dependencia">
                               <i class="fas fa-check"></i>
                           </button>`;

                    const btnEliminar = `<button type="button"
                                                class="btn btn-danger btn-sm btnEliminarDependencia"
                                                data-id-dependencia="${idDep}"
                                                data-nombre-dependencia="${nombreSeguro}"
                                                title="Eliminar dependencia">
                                            <i class="fas fa-trash"></i>
                                        </button>`;

                    return `
                        <div class="btn-group">
                            <a href="dependenciasForm?id=${idDep}"
                               class="btn btn-info btn-sm mr-1"
                               title="Modificar dependencia y encargado">
                                <i class="fas fa-edit"></i>
                            </a>
                            ${btnEstado}
                            ${btnEliminar}
                        </div>
                    `;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            decimal: "",
            emptyTable: "No hay dependencias institucionales registradas",
            info: "Mostrando _START_ a _END_ de _TOTAL_ dependencias",
            infoEmpty: "Mostrando 0 a 0 de 0 dependencias",
            infoFiltered: "(filtrado de un total de _MAX_ dependencias)",
            lengthMenu: "Mostrar _MENU_ dependencias",
            loadingRecords: "Cargando dependencias desde la API...",
            processing: "Procesando petición...",
            search: "Buscar:",
            zeroRecords: "No se encontraron coincidencias",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });
}

/*=============================================
2. CARGAR ESTADÍSTICAS (KPIs)
=============================================*/
function cargarEstadisticasDependencias() {
    fetch(`${API_DEPENDENCIAS_URL}/stats`)
        .then(response => response.json())
        .then(res => {
            if (res.success && res.data) {
                $("#kpiTotalDependencias").text(res.data.total ?? 0);
                $("#kpiDependenciasActivas").text(res.data.activas ?? 0);
                $("#kpiDependenciasInactivas").text(res.data.inactivas ?? 0);
                if ($("#kpiConEncargado").length > 0) {
                    $("#kpiConEncargado").text(res.data.conEncargado ?? 0);
                }
            }
        })
        .catch(err => {
            console.error("Error al cargar KPIs de dependencias:", err);
        });
}

/*=============================================
3. CARGAR CATÁLOGO DE USUARIOS PARA ENCARGADO
=============================================*/
function cargarCatalogoUsuariosParaSelect(callback) {
    fetch(API_USUARIOS_URL)
        .then(res => res.json())
        .then(res => {
            if (res.success && Array.isArray(res.data)) {
                let options = '<option value="">-- Sin encargado asignado (Opcional) --</option>';
                res.data.forEach(u => {
                    const rolTexto = u.rol ? ` (${u.rol})` : "";
                    const depActual = u.dependencia ? ` [${u.dependencia}]` : "";
                    options += `<option value="${u.id_usuario}">${u.nombre}${rolTexto} &mdash; ${u.correo}${depActual}</option>`;
                });
                $(".selectUsuariosEncargado").html(options);
            }
            if (typeof callback === "function") {
                callback();
            }
        })
        .catch(err => {
            console.error("Error al cargar catálogo de usuarios:", err);
            if (typeof callback === "function") {
                callback();
            }
        });
}

/*=============================================
4. FORMULARIO DE CREAR / EDITAR DEPENDENCIA
=============================================*/
function inicializarFormularioDependencia() {
    const params = new URLSearchParams(window.location.search);
    const idDependencia = parseInt(params.get("id"), 10);

    // Cargar usuarios en el desplegable primero
    cargarCatalogoUsuariosParaSelect(function() {
        if (!isNaN(idDependencia) && idDependencia > 0) {
            $("#tituloFormularioDep").html('<i class="fas fa-edit mr-2"></i> Modificar Dependencia y Encargado');
            $("#idDependenciaFormulario").val(idDependencia);
            cargarDatosDependencia(idDependencia);
        }
    });

    $("#frmGuardarDependencia").off("submit").on("submit", function(e) {
        e.preventDefault();

        const id = parseInt($("#idDependenciaFormulario").val(), 10);
        const esEdicion = (!isNaN(id) && id > 0);

        const payload = {
            nombre:                 $("#nombreDependencia").val().trim(),
            codigo:                 $("#codigoDependencia").val().trim().toUpperCase(),
            dependencia:            $("#nombreDependencia").val().trim(),
            tipo:                   $("#tipoDependencia").val(),
            descripcion:            $("#descripcionDependencia").val().trim(),
            estado:                 $("#estadoDependencia").val(),
            id_usuario_responsable: $("#encargadoDependencia").val() || null
        };

        if (!payload.nombre || !payload.codigo) {
            Swal.fire({
                icon: "warning",
                title: "Campos requeridos",
                text: "El nombre y el código/sigla de la dependencia son obligatorios."
            });
            return;
        }

        const url = esEdicion ? `${API_DEPENDENCIAS_URL}/${id}` : API_DEPENDENCIAS_URL;
        const metodo = esEdicion ? "PUT" : "POST";

        const $btn = $("#btnGuardarDepForm");
        const btnHtmlOriginal = $btn.html();
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

        fetch(url, {
            method: metodo,
            headers: {
                "Content-Type": "application/json; charset=UTF-8"
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            if ((status === 200 || status === 201) && body.success) {
                Swal.fire({
                    icon: "success",
                    title: esEdicion ? "¡Dependencia actualizada!" : "¡Dependencia creada!",
                    text: body.message || "La información de la dependencia y su encargado se registraron con éxito.",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "dependencias";
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "No fue posible guardar",
                    text: body.message || "Ocurrió un error inesperado al procesar la dependencia."
                });
            }
        })
        .catch(err => {
            console.error("Error de comunicación:", err);
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo establecer comunicación con el servidor de la API."
            });
        })
        .finally(() => {
            $btn.prop("disabled", false).html(btnHtmlOriginal);
        });
    });
}

function cargarDatosDependencia(id) {
    fetch(`${API_DEPENDENCIAS_URL}/${id}`)
        .then(response => response.json())
        .then(res => {
            if (res.success && res.data) {
                const dep = res.data;
                $("#nombreDependencia").val(dep.nombre || dep.dependencia || "");
                $("#codigoDependencia").val(dep.codigo || dep.dependencia || "");
                $("#tipoDependencia").val(dep.tipo || "operativa");
                $("#descripcionDependencia").val(dep.descripcion || "");
                $("#estadoDependencia").val(dep.estado || "Activo");

                if (dep.id_usuario_responsable) {
                    $("#encargadoDependencia").val(String(dep.id_usuario_responsable));
                } else {
                    $("#encargadoDependencia").val("");
                }
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Dependencia no encontrada",
                    text: res.message || "No se encontraron los datos de la dependencia solicitada."
                }).then(() => {
                    window.location.href = "dependencias";
                });
            }
        })
        .catch(err => {
            console.error("Error al cargar datos de la dependencia:", err);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No fue posible recuperar la información de la dependencia."
            });
        });
}

/*=============================================
5. EVENTOS DE TABLA (CAMBIAR ESTADO Y ELIMINAR)
=============================================*/
function configurarEventosTabla() {
    // Cambio atómico de estado
    $("#tblDependencias").on("click", ".btnEstadoDependencia", function() {
        const id = $(this).data("id-dependencia");
        const nuevoEstado = $(this).data("estado-dependencia");

        fetch(`${API_DEPENDENCIAS_URL}/${id}/estado`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json; charset=UTF-8"
            },
            body: JSON.stringify({ estado: nuevoEstado })
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const Toast = Swal.mixin({
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 2000
                });
                Toast.fire({
                    icon: "success",
                    title: `Dependencia actualizada a ${nuevoEstado}`
                });

                tablaDependencias.ajax.reload(null, false);
                cargarEstadisticasDependencias();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.message || "No fue posible cambiar el estado de la dependencia."
                });
            }
        })
        .catch(err => {
            console.error("Error al cambiar estado:", err);
            Swal.fire({
                icon: "error",
                title: "Error de red",
                text: "No fue posible actualizar el estado en el servidor."
            });
        });
    });

    // Eliminación
    $("#tblDependencias").on("click", ".btnEliminarDependencia", function() {
        const id = $(this).data("id-dependencia");
        const nombre = $(this).data("nombre-dependencia");

        Swal.fire({
            title: "¿Está seguro de eliminar esta dependencia?",
            text: `Se eliminará la dependencia "${nombre}". Esta acción no se puede deshacer.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${API_DEPENDENCIAS_URL}/${id}`, {
                    method: "DELETE"
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(({ status, body }) => {
                    if (status === 200 && body.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Eliminada",
                            text: "La dependencia fue eliminada correctamente del sistema.",
                            timer: 2000,
                            showConfirmButton: false
                        });

                        tablaDependencias.ajax.reload(null, false);
                        cargarEstadisticasDependencias();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "No se pudo eliminar",
                            text: body.message || "La dependencia no pudo ser eliminada."
                        });
                    }
                })
                .catch(err => {
                    console.error("Error al eliminar dependencia:", err);
                    Swal.fire({
                        icon: "error",
                        title: "Error de red",
                        text: "Error al comunicar con el servidor para la eliminación."
                    });
                });
            }
        });
    });
}
