let getUrl  = window.location;
let baseUrl = getUrl.protocol + "//" + getUrl.host + "/" + getUrl.pathname.split('/')[1] + "/" + getUrl.pathname.split('/')[2];

const generateCsv = () => `<a href="${baseUrl}?controller=AdminModules&amp;token=${token_url}&amp;configure=agzipcodezones&amp;downloadSampleCSV&amp;fretes"> aqui</a>`;

const createButton = () => $('.dropdown-menu').append('<li><a href="#" title="Importar" class="delete" id="exportar"><i class="icon-upload"></i> Importar</a></li>');

const createModal = (id) => {

    $('body').append("<div id='modal-agzipcodezones'></div>");
    $('#modal-agzipcodezones').empty();
    $('#modal-agzipcodezones').append(`
        <div class="modal fade" id="upload" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <form method="POST" action="#" enctype="multipart/form-data" id="send-agzipcodezones">
                        <div class="modal-header">
                            <div class="bootstrap modal-title" id="exampleModalLongTitle">
                                <div class="alert alert-info">
                                    <ul class="list-unstyled">
                                        <li>
                                            <div>
                                                <strong>Você pode importar as tabelas de fretes para a sua loja através de um arquivo CSV. Para baixar o arquivo de modelo clique ${generateCsv()}.Essa operação irá excluir toda a tabela de fretes da transportadora. Se utilizar acentos o arquivo precisa estar no formato UTF-8.</strong>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="id" value="${id}" required>
                            <input type="file" name="file-agzipcodezones" id="file-agzipcodezones" required>
                        </div>
                            <div class="modal-footer">
                                <button type="submit" id="submit" class="btn-carriers"><div id="btn-init"><i class="icon-upload"></i> Importar</div> <div id="btn-loader" style="display: none"><i class="icon-refresh"></i> Aguarde, carregando...</div></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `);

    $('#upload').modal('show');
}

const message = (message, type) => {

    event.stopPropagation();

    if (type === 'notice') {

        $.growl.notice({ title: '', size: "large", message: message });
    } else {

        $.growl.error({ title: '', size: "large", message: message });
    }
}

$(document).ready(function () {

    createButton();

    $(document).on('click', '#exportar', function (e) {

        const id = $(this).closest("tr").find("input[type='checkbox']").val();
        createModal(id);
    });

    $(document).on("submit", "#send-agzipcodezones", function (t) {
        t.preventDefault();
        let data = new FormData(this);

        $.ajax({
            url: `${baseUrl}?ajax=true&controller=AgZipcodeZonesInterval&token=${token_ajax}&action=UploadCsv`,
            method: "post",
            data: data,
            mimeType: "multipart/form-data",
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function (load) {
                $('#btn-init').css('display', 'none');
                $('#btn-loader').css('display', 'block');
                $('#file-agzipcodezones').prop("disabled", true);
                $('#submit').prop("disabled", true);
                return;
            },
            success: function (data) {

                if (data.type === 'success') {
                    message(data.message, 'notice');
                    $('#send-agzipcodezones').trigger("reset");
                }
                if (data.type === 'error') {
                    message(data.message, 'error');
                }
                return;
            },
            complete: function () {
                $('#btn-init').css('display', 'block');
                $('#btn-loader').css('display', 'none');
                $('#file-agzipcodezones').prop("disabled", false);
                $('#submit').prop("disabled", false);
            },
            error: function (error) {
                message(error, 'error');
                return;
            }
        });
    });
});