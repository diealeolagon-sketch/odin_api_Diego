<!-- Content Header (Encabezado de la página) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1><i class="fas fa-building mr-2"></i> Gestión de Dependencias</h1>
        <p class="text-muted mb-0">
          RF-40 · RF-41 · RNF-14 — Administre las dependencias y asigne los usuarios responsables de cada área institucional.
        </p>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="inicio"><i class="fas fa-home mr-1"></i> Inicio</a></li>
          <li class="breadcrumb-item active">Dependencias</li>
        </ol>
      </div>
    </div>
  </div>
</section>

<!-- Main content -->
<section class="content">
  <div class="container-fluid">

    <!-- Tarjetas de métricas informativas (Actualizadas asíncronamente vía API REST) -->
    <div class="row">
      <div class="col-lg-3 col-sm-6 col-12">
        <div class="small-box bg-info">
          <div class="inner">
            <h3 id="kpiTotalDependencias">--</h3>
            <p>Total dependencias</p>
          </div>
          <div class="icon">
            <i class="fas fa-building"></i>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-sm-6 col-12">
        <div class="small-box bg-success">
          <div class="inner">
            <h3 id="kpiDependenciasActivas">--</h3>
            <p>Dependencias activas</p>
          </div>
          <div class="icon">
            <i class="fas fa-check-circle"></i>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-sm-6 col-12">
        <div class="small-box bg-warning">
          <div class="inner">
            <h3 id="kpiDependenciasInactivas">--</h3>
            <p>Dependencias inactivas</p>
          </div>
          <div class="icon">
            <i class="fas fa-ban"></i>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-sm-6 col-12">
        <div class="small-box bg-primary">
          <div class="inner">
            <h3 id="kpiConEncargado">--</h3>
            <p>Con encargado asignado</p>
          </div>
          <div class="icon">
            <i class="fas fa-user-shield"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- TARJETA PRINCIPAL CON TABLA VACÍA PARA DATATABLES AJAX -->
    <div class="card card-primary card-outline shadow">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title">
          <i class="fas fa-building mr-2"></i>
          Listado de Dependencias Institucionales
        </h3>
        <div class="card-tools ml-auto">
          <a href="dependenciasForm" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Nueva Dependencia
          </a>
        </div>
      </div>

      <div class="card-body">
        <table id="tblDependencias" class="table table-bordered table-hover table-striped dt-responsive nowrap" style="width:100%">
          <thead class="bg-primary text-white">
            <tr>
              <th width="70">ID</th>
              <th width="130">Código / Sigla</th>
              <th>Nombre</th>
              <th>Encargado / Responsable</th>
              <th>Descripción</th>
              <th width="90" class="text-center">Usuarios</th>
              <th width="100" class="text-center">Estado</th>
              <th width="180" class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <!-- El contenido se inyecta dinámicamente vía AJAX desde vistas/js/dependencias.js -->
          </tbody>
          <tfoot>
            <tr>
              <th>ID</th>
              <th>Código / Sigla</th>
              <th>Nombre</th>
              <th>Encargado / Responsable</th>
              <th>Descripción</th>
              <th class="text-center">Usuarios</th>
              <th class="text-center">Estado</th>
              <th class="text-center">Acciones</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div>
</section>

<!-- Inclusión modular del script del cliente -->
<script src="vistas/js/dependencias.js"></script>
