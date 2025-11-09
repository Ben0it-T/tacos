
$( document ).ready(function() {
    new DataTable('#datatable', {
        info: false,
        ordering: true,
        paging: false,
        columnDefs: [
            {targets: '_all', className: 'dt-head-left'},
            {targets: 'nosearch', searchable: false},
            {targets: 'sortable', orderable: true},
            {targets: '_all', orderable: false}
        ],
        language: {
            search: '<i class="bi bi-search"></i>',
            zeroRecords: '...',
            emptyTable: '...'
        },
        order: [[0, '']],
    });
});
