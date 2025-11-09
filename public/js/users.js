$( document ).ready(function() {
    new DataTable('#users', {
        info: false,
        ordering: false,
        paging: false,
        columnDefs: [
            {targets: 'nosearch', searchable: false},
            {targets: '_all', type: 'string-utf8'}
        ],
        language: {
            search: '<i class="bi bi-search"></i>',
            zeroRecords: '...',
            emptyTable: '...'
        }
    });
});
