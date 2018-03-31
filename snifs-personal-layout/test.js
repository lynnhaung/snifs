jQuery(document).ready(function()
{
    var team = "D";
        var sendPerson = {
            team: team,
            number: '13'
        };
    $.post('personal_table_get_data.php',sendPerson, function(result, status, xhr)
    {
        row_table_person = obj['row_table_person'];
        console.log(result);
    });
});
