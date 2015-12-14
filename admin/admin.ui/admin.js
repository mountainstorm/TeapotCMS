var app = angular.module('TeapotApp', ['formly', 'ngAria', 'ngAnimate', 'ngRoute', 'ngMaterial'])

loadSite().then(bootstrapApp)

/* load the initial site config */
function loadSite () {
    var initInjector = angular.injector(["ng"])
    var $http = initInjector.get("$http")
    return $http.get('api/v1/site').then(function (response) {
        if (response.data.authorized == false) {
            console.log('User is unauthoried')
            window.location.href = 'api/v1/logout'            
        }
        app.constant('config', response.data)
        // XXX: redirect to the first collection

    }, function (response) {
        /* something really bad has happended - logged out */
        console.log('Unable to receive site data')
        window.location.href = 'api/v1/logout'
    })
}

/* bootstrap the angular app */
function bootstrapApp () {
    angular.element(document).ready(function() {
        angular.bootstrap(document, ['TeapotApp'])
    })
}

/* configure themes */
app.config(function ($mdThemingProvider, $routeProvider, $httpProvider, $mdDateLocaleProvider) {
    $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

    $mdThemingProvider.theme('default')
        .primaryPalette('blue', {
            'default': 'A200'
        })
    $mdThemingProvider.theme('docs-dark', 'default')
        .primaryPalette('yellow')
        .dark()

    /* configure routes */
    $routeProvider
        .when('/_logout', {
            redirectTo: function () {
                window.location.href = 'api/v1/logout'
            }
        })
        .when('/:form', {
            templateUrl: 'form.html',
            controller: 'FormController',
            resolve: { 
                response: function ($http, $route) {
                    return $http.get(
                        'api/v1/forms/' +
                        $route.current.params.form
                    ).then(function (response) {
                        return response
                    })
                }
            }
        })
        .when('/:form/:_id', {
            templateUrl: 'form.html',
            controller: 'FormController',
            resolve: { 
                response: function ($http, $route) {
                    return $http.get(
                        'api/v1/forms/' + 
                        $route.current.params.form +
                        '/' +
                        $route.current.params._id 
                    ).then(function (response) {
                        return response
                    })
                }
            }
        })
        .otherwise({
            redirectTo: '/'
        })


        $mdDateLocaleProvider.parseDate = function(dateString) {
            var m = moment(dateString, 'L', true)
            console.log('parsing: ' + dateString)
            return m.isValid() ? m.toDate() : new Date(NaN)
        }
        $mdDateLocaleProvider.formatDate = function(date) {
            return moment(date).format('L')
        }
})