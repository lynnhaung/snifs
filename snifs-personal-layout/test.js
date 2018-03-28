jQuery(document).ready(function()
{
    jQuery.post('./personal_get_data.php', function(result, status, xhr)
{
    obj = JSON.parse(result);


    // console.log(status, result);

    row_node_person = Object.keys(obj["row_node_person"]).map(function(key)
    {
        return obj["row_node_person"][key];

 });
 console.log(row_node_person);
    // console.log(person_number);
});
});
