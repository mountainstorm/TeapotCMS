var app = angular.module('TeapotApp')

app.controller('UIController', function ($scope, $route, $mdSidenav, $mdDialog, $location, config) {
    /* save the route object so we can get info from the current route */
    $scope.$route = $route
    /* save the sidebar reference for use in the mini-toolbar markup */
    $scope.sidenav = $mdSidenav

    /* frontend site information / collection list etc */
    $scope.site = config

    /* current (highlghted) collection */
    $scope.current = null

    $scope.progress = 0

    $scope.$on('$routeChangeStart', function(event, next, current) { 
        /* lookup te collection and assign current */
        $scope.current = null
        if (next.params.form) {
            angular.forEach($scope.site.collections, function (value, key) {
                /* collection routes might be to specific element - check to first / */
                var cur = value.form.substring(0, value.form.indexOf('/'))
                if (cur == next.params.form) {
                    $scope.current = value
                }
            })
            if ($scope.current == null && next.params.form !== '_logout') {
                /* no current - route invalid so stop loading form */
                $scope.progress = 0
                event.preventDefault()
                var alert = $mdDialog.alert()
                    .title('The collection does not exist?')
                    .content('The collection "' + next.params.form + '" specified in the url does not exist')
                    .ok('Bum!')
                $mdDialog.show(alert).then(function() {
                    /* redirect to root */
                    $location.path('/')
                })
            }
        }
    })

    $scope.$on('$routeChangeSuccess', function(event, current, previous) {
        $scope.progress = 100
    })

    $scope.$on('$routeChangeError', function(event, current, previous, response) {
        if (response.status == 401) {
            /* you've been logged out */
            window.location.href = 'api/v1/logout'
        } else {
            $scope.progress = 0
            var alert = $mdDialog.alert()
                .title('An error occurred')
                .content('Error: ' + response.data)
                .ok('Bum!')
            $mdDialog.show(alert).then(function() {
                /* redirect to root */
                $location.path('/')
            })
        }
    })

    $scope.topCollection = function (collection) {
        return !collection.bottom
    }

    $scope.goToCollection = function (form) {
        $scope.progress = 30 /* start progress bar */
        if ($scope.$route.current &&
                $scope.$route.current.scope &&
                $scope.$route.current.scope.saveRequired) {
            console.log('saveRequired: ' + $scope.$route.current.scope.saveRequired)
            /* content has changed - ask user if they want to save */
            var confirm = $mdDialog.confirm()
                .title('Would you like to save your changes?')
                .content('Would you like to save the changes you have made.')
                .ok('Save changes')
                .cancel('Discard changes')
            $mdDialog.show(confirm).then(function() {
                $scope.$route.current.scope.save().then(function () {
                    /* asked to save and save complete */
                    $location.path('/' + form)
                })
            }, function() {
                /* don't want to save */
                $location.path('/' + form)
            })
        } else {
            /* nothing to save */
            $location.path('/' + form)
        }     
    }

    $scope.openFrontend = function (slug) {
        /* open site frontend in a new tab */
        var url = $scope.site.url
        if (slug && slug.length > 0) {
            if (slug.indexOf('http://') == 0 || slug.indexOf('https://') == 0) {
                url = slug /* full url */
            } else {
                /* join the slug, if it doesn't start with a '#' or '/'' and the 
                 * url doesn't end in a '/' we need to add one */
                if (slug[0] == '#') {
                    /* hash slug - just add it */
                    url += slug
                } else if (url[url.length-1] == '/' && slug[0] == '/') {
                    /* both have slashes - only add one */
                    url += slug.substring(1)
                } else if (url[url.length-1] != '/' && slug[0] != '/') {
                    /* nobody has slashes */
                    url += '/' + slug
                } else {
                    /* one has a slash */
                    url += slug
                }
            }
        }
        window.open(url)
    }
})