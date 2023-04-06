# MySQL Deployment

```

UPDATE user__field_member_id SET field_member_id_value=2000001 WHERE entity_id=0;

SELECT  entity_id, field_member_id_value, COUNT(field_member_id_value) FROM user__field_member_id GROUP BY field_member_id_value HAVING COUNT(field_member_id_value) > 1;


ALTER TABLE user__field_member_id ADD UNIQUE (field_member_id_value);


## ALTER
select MAX(field_member_id_value) from user__field_member_id;


field_member_id_value=2100098


select entity_id from user__field_member_id where field_member_id_value=2100078;
```
