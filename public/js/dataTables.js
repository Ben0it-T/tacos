
$( document ).ready(function() {
    new DataTable('#datatable', {
        info: false,
        ordering: false,
        paging: false,
        columnDefs: [
            {targets: 'nosearch', searchable: false},
            {targets: '_all', className: 'dt-head-left'}
        ],
        language: {
            search: '<i class="bi bi-search"></i>',
            zeroRecords: '...',
            emptyTable: '...'
        }
    });
});
