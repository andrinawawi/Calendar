import axios from 'axios';

//create a variable local path, in production there will be antohter path
var localPath = "";
if(process.env.NODE_ENV == 'development'){
    localPath= "/tenniscalendar/public_html"
}

export default {
    
    getData(action) {
        return axios({
            method: 'get',
            url : localPath +  '/api/' + action
        })
        .then(function (response) {
            return response.data;
        })
        .catch(function (error) {
            console.log(error);
        });
    },

    //return all the data with response codes
    getDataAdv(action) {
        return axios({
            method: 'get',
            url : localPath +  '/api/' + action
        })
        .then(function (response) {
            return response;
        })
        .catch(function (error) {
            console.log(error);
        });
    },

    postData(action, fields){
        return axios({
            method: 'POST',
            url : localPath +  '/api/' + action,
            data: fields,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then(function (response) {
            return response.data;
        })
        .catch(error => {
            if (error.response.status === 422) {
               return Promise.reject(error.response.data.errors || {})
            };
        });
    },

    updateData(action, fields){
        return axios({
            method: 'POST',
            url :  localPath +  '/api/' + action,
            data: fields,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then(function (response) {
            return response.data;
        })
        .catch(error => {
            if (error.response.status === 422) {
               return Promise.reject(error.response.data.errors || {})
            };
        });
    },

    deleteData(action, id){
        return axios({
            method: 'DELETE',
            url : localPath +  '/api/' + action,
            data: id,
        })
        .then(function (response) {
           return response.data;
        })
        .catch(error => {
            if (error.response.status === 403) {
                return error.response;
            }else if (error.response.status === 422) {
               return Promise.reject(error.response.data.errors || {})
            };
        });
    },
}
